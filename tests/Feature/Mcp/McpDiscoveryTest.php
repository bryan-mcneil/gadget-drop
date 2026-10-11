<?php

namespace Tests\Feature\Mcp;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Phase 4.3 — discoverability: the search tool, the methodology resource
 * (rendered from the SAME partial /how-we-review includes), and the /for-ai
 * docs page + llms.txt/sitemap entries humans and crawlers find it through.
 */
class McpDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function reviewedProduct(string $name, ?string $brand = null, ?string $asin = null): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'brand' => $brand,
            'asin' => $asin ?? "B00SRCH{$i}0",
            'affiliate_url' => 'https://www.amazon.com/dp/B00SRCH',
            'price' => 99,
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$name} Review",
            'slug' => str_replace(' ', '-', strtolower($name)).'-review-'.$i,
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
            'id' => 7,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);
    }

    private function toolPayload(TestResponse $response): array
    {
        $response->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true) ?? [];
    }

    public function test_search_matches_name_and_brand_case_insensitively(): void
    {
        $this->reviewedProduct('WH-1000XM5 Headphones', 'Sony');
        $this->reviewedProduct('SRS-XB100 Speaker', 'Sony');
        $this->reviewedProduct('Unrelated Charger', 'Anker');

        $byBrand = $this->toolPayload($this->callTool('search_tracked_products', ['query' => 'sony']));
        $this->assertSame(2, $byBrand['count']);

        $byPartialName = $this->toolPayload($this->callTool('search_tracked_products', ['query' => 'wh-1000']));
        $this->assertSame(1, $byPartialName['count']);

        $match = $byPartialName['matches'][0];
        $this->assertSame('WH-1000XM5 Headphones', $match['name']);
        $this->assertSame('Sony', $match['brand']);
        $this->assertArrayHasKey('asin', $match);
        $this->assertArrayHasKey('has_stats', $match);
        $this->assertStringContainsString('/posts/', $match['review_url']);
    }

    public function test_search_matches_an_exact_asin(): void
    {
        $this->reviewedProduct('Alpha Widget', 'TestBrand', 'B00EXACT99');

        $payload = $this->toolPayload($this->callTool('search_tracked_products', ['query' => 'b00exact99']));

        $this->assertSame(1, $payload['count']);
        $this->assertSame('Alpha Widget', $payload['matches'][0]['name']);
    }

    public function test_search_caps_results_at_ten(): void
    {
        foreach (range(1, 12) as $n) {
            $this->reviewedProduct("Cable Pack {$n}");
        }

        $payload = $this->toolPayload($this->callTool('search_tracked_products', ['query' => 'Cable Pack']));

        $this->assertSame(10, $payload['count']);
        $this->assertCount(10, $payload['matches']);
    }

    public function test_search_requires_a_usable_query(): void
    {
        $response = $this->callTool('search_tracked_products', ['query' => 'a']);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('result.isError'));
    }

    public function test_search_with_no_matches_returns_an_honest_empty_set(): void
    {
        $response = $this->callTool('search_tracked_products', ['query' => 'nothing tracked']);
        $payload = $this->toolPayload($response);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame(0, $payload['count']);
        $this->assertSame([], $payload['matches']);
    }

    public function test_resources_list_advertises_the_methodology(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'resources/list'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'methodology'])
            ->assertJsonFragment(['uri' => 'gadgetdrop://methodology/deal-verdicts']);
    }

    public function test_reading_the_methodology_resource_returns_the_shared_copy(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'resources/read',
            'params' => ['uri' => 'gadgetdrop://methodology/deal-verdicts'],
        ]);

        $response->assertOk();

        $text = (string) $response->json('result.contents.0.text');

        // Headings survive as markdown; the gate numbers come from the same
        // PriceIntel constants the page quotes, so they can't drift.
        $this->assertStringContainsString('## How our price data works', $text);
        $this->assertStringContainsString('The honesty gates', $text);
        $this->assertStringContainsString('14 days', $text);
        $this->assertStringContainsString('scrape Amazon pages', $text);
        $this->assertStringContainsString('#deal-verdicts', $text);
        // Strip really stripped: no tags, no Blade class soup.
        $this->assertStringNotContainsString('<', $text);
        $this->assertStringNotContainsString('text-gray-600', $text);
    }

    public function test_the_how_we_review_page_still_renders_the_shared_partial(): void
    {
        $this->get('/how-we-review')
            ->assertOk()
            ->assertSee('id="deal-verdicts"', false)
            ->assertSee('The honesty gates', false)
            ->assertSee('scrape Amazon pages', false);
    }

    public function test_for_ai_page_renders_with_meta_and_config_snippets(): void
    {
        $this->get('/for-ai')
            ->assertOk()
            ->assertSee('Price-Truth MCP Server', false)
            ->assertSee(url('/mcp'), false)
            ->assertSee('claude mcp add --transport http gadgetdrop', false)
            ->assertSee('30 requests/minute', false)
            ->assertSee('rel="canonical" href="'.route('for-ai').'"', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_for_ai_is_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('for-ai'), false);
    }

    public function test_llms_txt_advertises_the_mcp_surface(): void
    {
        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('For AI agents')
            ->assertSee(route('for-ai'))
            ->assertSee(url('/mcp'));
    }
}
