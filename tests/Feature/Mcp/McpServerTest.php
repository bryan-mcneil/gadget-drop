<?php

namespace Tests\Feature\Mcp;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The throttle counters live in the (array, in test) cache and are keyed
        // by IP, which is shared across tests. Start every test from a clean
        // slate so the rate-limit test is deterministic.
        Cache::flush();
    }

    /** A minimal, side-effect-free JSON-RPC request for throttle probing. */
    private function toolsListBody(int $id = 1): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'method' => 'tools/list'];
    }

    private function makeProducts(int $count): void
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'category_id' => $category->id,
                'name' => "Gadget {$i}",
                'affiliate_url' => "https://www.amazon.com/dp/B00PING{$i}",
                'price' => 10 * $i,
            ]);
        }
    }

    public function test_initialize_handshake_returns_server_identity_and_instructions(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            // protocolVersion omitted on purpose: the server defaults to its
            // first supported version, so the test isn't coupled to a spec date.
            'params' => ['clientInfo' => ['name' => 'phpunit', 'version' => '1.0']],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'GadgetDrop Price Truth')
            ->assertJsonPath('result.serverInfo.version', '1.0.0');

        $instructions = $response->json('result.instructions');
        $this->assertStringContainsString('commission', $instructions);
        $this->assertStringContainsString('how-we-review#deal-verdicts', $instructions);
        $this->assertStringContainsString('NOT live Amazon prices', $instructions);
    }

    public function test_the_methodology_anchor_advertised_in_instructions_exists(): void
    {
        // The server instructions point agents at /how-we-review#deal-verdicts;
        // guard that the anchor actually exists so the shipped URL isn't broken.
        $this->get('/how-we-review')
            ->assertOk()
            ->assertSee('id="deal-verdicts"', false);
    }

    public function test_tools_list_contains_ping(): void
    {
        $this->postJson('/mcp', $this->toolsListBody())
            ->assertOk()
            ->assertJsonFragment(['name' => 'ping']);
    }

    public function test_ping_reports_the_tracked_product_count(): void
    {
        $this->makeProducts(3);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'ping', 'arguments' => []],
        ]);

        $response->assertOk();

        $text = $response->json('result.content.0.text');
        $this->assertStringContainsString('online', $text);
        $this->assertStringContainsString('tracking 3 products', $text);
    }

    public function test_responses_are_not_cacheable(): void
    {
        $response = $this->postJson('/mcp', $this->toolsListBody());

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_exceeding_the_rate_limit_returns_429(): void
    {
        // 30 requests per minute are allowed; the 31st is throttled.
        for ($i = 1; $i <= 30; $i++) {
            $this->postJson('/mcp', $this->toolsListBody($i))->assertOk();
        }

        $this->postJson('/mcp', $this->toolsListBody(31))->assertStatus(429);
    }

    public function test_mcp_post_route_is_outside_the_web_group_so_csrf_does_not_apply(): void
    {
        /** @var Route|null $route */
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn (Route $r): bool => $r->uri() === 'mcp' && in_array('POST', $r->methods(), true),
        );

        $this->assertNotNull($route, 'The POST /mcp route should be registered.');

        // The web group is where session + VerifyCsrfToken live. laravel/mcp
        // loads routes/ai.php outside it, so an agent can POST token-less.
        $middleware = $route->middleware();
        $this->assertNotContains('web', $middleware);
        foreach ($middleware as $m) {
            $this->assertStringNotContainsStringIgnoringCase('VerifyCsrfToken', (string) $m);
        }

        // Behavioural confirmation: a token-less, session-less POST is accepted.
        $this->postJson('/mcp', $this->toolsListBody())->assertOk();
    }
}
