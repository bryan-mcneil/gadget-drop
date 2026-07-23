<?php

namespace Tests\Feature\Mcp;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\ReleaseCycle;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Plan 06 §Phase 6.5: the get_buy_or_wait_verdict tool. Mirrors McpToolsTest's
 * shape. The load-bearing assertions are the honesty ones: the response must
 * carry the cycle's source and verification date, must never claim high
 * confidence on thin data, and an unknown line must name what we do cover.
 */
class McpBuyOrWaitToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function cycle(array $overrides = []): ReleaseCycle
    {
        return ReleaseCycle::create(array_merge([
            'name' => 'iPhone',
            'slug' => 'iphone',
            'typical_month' => 9,
            'cadence_months' => 12,
            'last_release_name' => 'iPhone 17',
            'last_release_at' => now()->subMonths(10)->toDateString(),
            'next_expected_note' => 'Announced every September since 2012.',
            'source_url' => 'https://www.apple.com/newsroom/2025/09/apple-debuts-iphone-17/',
            'verified_at' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    private function trackedProduct(string $name, float $historic, float $current, Category $category): Product
    {
        static $i = 0;
        $i++;

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'brand' => 'Apple',
            'asin' => "B00MCPBW{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00MCPBW',
            'price' => $historic,
        ]);

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $historic,
            'source' => 'manual',
            'created_at' => now()->subDays(60),
        ]);

        if ($current !== $historic) {
            $product->update(['price' => $current]);
        }

        PriceIntel::flush($product->id);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$name} Review",
            'slug' => \Illuminate\Support\Str::slug($name).'-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $product;
    }

    private function callTool(string $name, array $arguments = []): TestResponse
    {
        return $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 42,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);
    }

    private function toolPayload(TestResponse $response): array
    {
        $response->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true) ?? [];
    }

    public function test_the_tool_is_advertised(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'get_buy_or_wait_verdict']);
    }

    public function test_a_line_resolves_by_slug_name_and_partial_name(): void
    {
        $this->cycle();

        foreach (['iphone', 'iPhone', 'iphone 17 pro'] as $query) {
            $payload = $this->toolPayload($this->callTool('get_buy_or_wait_verdict', ['query' => $query]));

            $this->assertSame('iphone', $payload['slug'], "query {$query}");
        }
    }

    public function test_the_verdict_carries_its_source_verification_date_and_confidence(): void
    {
        $this->cycle();

        $response = $this->callTool('get_buy_or_wait_verdict', ['query' => 'iPhone']);
        $payload = $this->toolPayload($response);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame('wait_for_refresh', $payload['verdict']);
        $this->assertSame('Wait for the refresh', $payload['verdict_label']);
        // Cycle-only (no reviewed product on this line), so never "high".
        $this->assertSame('medium', $payload['confidence']);
        $this->assertNull($payload['price']);

        $this->assertSame('https://www.apple.com/newsroom/2025/09/apple-debuts-iphone-17/', $payload['cycle']['source_url']);
        $this->assertSame(now()->subMonth()->toDateString(), $payload['cycle']['verified_at']);
        $this->assertFalse($payload['cycle']['data_is_stale']);
        $this->assertSame('September', $payload['cycle']['typical_refresh_month']);
        $this->assertSame(12, $payload['cycle']['cadence_months']);
        $this->assertSame(now()->toDateString(), $payload['as_of']);

        $this->assertStringContainsString('/buy-or-wait/iphone', $payload['page_url']);
        $this->assertStringContainsString('#buy-or-wait', $payload['methodology_url']);
        $this->assertStringContainsString('commission', $payload['disclosure']);
        $this->assertStringContainsString('publishes nothing about unannounced products', $payload['note']);
    }

    public function test_the_sentence_is_the_hedged_server_assembled_one(): void
    {
        $this->cycle();

        $payload = $this->toolPayload($this->callTool('get_buy_or_wait_verdict', ['query' => 'iPhone']));

        $this->assertStringContainsString('Historically the iPhone line refreshes', $payload['sentence']);
        $this->assertStringContainsString('iPhone 17', $payload['sentence']);
        $this->assertStringContainsString('release timing alone', $payload['sentence']);
    }

    public function test_the_factors_travel_with_the_verdict(): void
    {
        $this->cycle();

        $payload = $this->toolPayload($this->callTool('get_buy_or_wait_verdict', ['query' => 'iPhone']));

        $this->assertSame(
            ['Release cadence', 'Current model', 'Tracked price'],
            array_column($payload['factors'], 'label'),
        );
        $this->assertStringContainsString('Announced every September since 2012.', $payload['factors'][0]['detail']);
        $this->assertSame('https://www.apple.com/newsroom/2025/09/apple-debuts-iphone-17/', $payload['factors'][0]['source_url']);
    }

    public function test_a_line_with_a_tracked_review_returns_the_shared_price_block(): void
    {
        $audio = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->cycle([
            'name' => 'AirPods Pro',
            'slug' => 'airpods-pro',
            'category_id' => $audio->id,
            'cadence_months' => 36,
            'last_release_name' => 'AirPods Pro 3',
        ]);
        $this->trackedProduct('AirPods Pro 3', 249, 199, $audio);

        $payload = $this->toolPayload($this->callTool('get_buy_or_wait_verdict', ['query' => 'airpods-pro']));

        $this->assertSame('buy', $payload['verdict']);
        $this->assertSame('high', $payload['confidence']);
        $this->assertNotNull($payload['price']);
        $this->assertSame('AirPods Pro 3', $payload['price']['product']['name']);
        $this->assertTrue($payload['price']['has_stats']);
        // The affiliate invariant: every product link routes through /out/.
        $this->assertStringContainsString('/out/', $payload['price']['affiliate_url']);
        $this->assertStringContainsString('/posts/', $payload['price']['review_url']);
    }

    public function test_stale_cycle_data_is_reported_and_caps_confidence(): void
    {
        $this->cycle(['verified_at' => now()->subMonths(9)->toDateString()]);

        $payload = $this->toolPayload($this->callTool('get_buy_or_wait_verdict', ['query' => 'iPhone']));

        $this->assertTrue($payload['cycle']['data_is_stale']);
        $this->assertNotSame('high', $payload['confidence']);
        $this->assertStringContainsString('re-checked', $payload['sentence']);
    }

    public function test_an_unknown_line_errors_and_names_what_we_do_cover(): void
    {
        $this->cycle();
        $this->cycle(['name' => 'Nintendo Switch', 'slug' => 'nintendo-switch']);

        $response = $this->callTool('get_buy_or_wait_verdict', ['query' => 'Dyson Airwrap']);

        $this->assertTrue((bool) $response->json('result.isError'));
        $text = (string) $response->json('result.content.0.text');
        $this->assertStringContainsString('does not track a release cycle', $text);
        $this->assertStringContainsString('iPhone', $text);
        $this->assertStringContainsString('Nintendo Switch', $text);
    }

    public function test_an_empty_or_oversized_query_is_rejected(): void
    {
        $this->cycle();

        $empty = $this->callTool('get_buy_or_wait_verdict', ['query' => '  ']);
        $this->assertTrue((bool) $empty->json('result.isError'));
        $this->assertStringContainsString('Lines we track', (string) $empty->json('result.content.0.text'));

        $long = $this->callTool('get_buy_or_wait_verdict', ['query' => str_repeat('a', 121)]);
        $this->assertTrue((bool) $long->json('result.isError'));
        $this->assertStringContainsString('capped at 120', (string) $long->json('result.content.0.text'));
    }

    public function test_llms_txt_and_for_ai_advertise_the_surface(): void
    {
        $this->cycle();

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee(route('buy-or-wait.index'), false)
            ->assertSee('no rumours or predictions', false);

        $this->get('/for-ai')
            ->assertOk()
            ->assertSee('get_buy_or_wait_verdict', false);
    }
}
