<?php

namespace Tests\Feature\Mcp;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The three core Price-Truth tools (Phase 4.2): resolver precedence, honesty
 * gates propagating, server-assembled verdict sentences, and the affiliate
 * invariant — every product link routes through /out/, NEVER raw amazon.com.
 */
class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Throttle counters + PriceIntel/deals caches share the array store.
        Cache::flush();
    }

    /**
     * A product with a published review and a controlled history: held at
     * $historic for 60 days, currently at $current (observer snapshots the
     * change), so PriceIntel's gates are open. Seeding mirrors DealsPageTest.
     */
    private function trackedProduct(string $name, float $historic, float $current, array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => $overrides['asin'] ?? "B00MCP{$i}00",
            'brand' => $overrides['brand'] ?? 'TestBrand',
            'affiliate_url' => 'https://www.amazon.com/dp/B00MCP',
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
            'slug' => str_replace(' ', '-', strtolower($name)).'-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $product;
    }

    /** A reviewed product whose only snapshot is today — honesty-gated. */
    private function gatedProduct(string $name): Product
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => 'B00GATED01',
            'affiliate_url' => 'https://www.amazon.com/dp/B00GATED01',
            'price' => 50,
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$name} Review",
            'slug' => str_replace(' ', '-', strtolower($name)).'-review',
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

    /** Decode the JSON payload a tool returned as its text content. */
    private function toolPayload(TestResponse $response): array
    {
        $response->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true) ?? [];
    }

    public function test_price_history_by_asin_returns_stats_and_safe_urls(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80, ['asin' => 'B00DROP001']);

        $response = $this->callTool('get_price_history', ['asin' => 'B00DROP001']);
        $payload = $this->toolPayload($response);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame('Dropped Widget', $payload['product']['name']);
        $this->assertEquals(80.0, $payload['current']);
        $this->assertNotNull($payload['checked_at']);
        $this->assertNotNull($payload['tracking_since']);
        $this->assertTrue($payload['has_stats']);
        // 80 IS the 90-day low (and the price varied), so the top tier applies.
        $this->assertSame('lowest', $payload['verdict']);
        $this->assertNotNull($payload['drop_pct']);
        $this->assertNotNull($payload['stats']['avg90']);
        $this->assertNotEmpty($payload['history']);
        $this->assertArrayHasKey('date', $payload['history'][0]);
        $this->assertArrayHasKey('price', $payload['history'][0]);
        $this->assertStringContainsString('/posts/dropped-widget-review', $payload['review_url']);
        $this->assertStringContainsString('/out/', $payload['affiliate_url']);
        $this->assertStringContainsString('commission', $payload['disclosure']);
        $this->assertStringContainsString('#deal-verdicts', $payload['methodology_url']);
    }

    public function test_no_raw_amazon_urls_anywhere_in_tool_responses(): void
    {
        // The seeded product's OWN affiliate_url is an amazon.com URL — the
        // response must expose only the /out/ redirect, never that raw URL.
        $this->trackedProduct('Dropped Widget', 100, 80, ['asin' => 'B00DROP001']);

        foreach ([
            ['get_price_history', ['asin' => 'B00DROP001']],
            ['get_deal_verdict', ['asin' => 'B00DROP001']],
            ['list_tracked_deals', []],
            ['search_tracked_products', ['query' => 'Dropped']],
        ] as [$tool, $arguments]) {
            $raw = (string) $this->callTool($tool, $arguments)->json('result.content.0.text');
            $this->assertStringNotContainsString('amazon.com', $raw, "{$tool} leaked a raw Amazon URL");
        }
    }

    public function test_asin_resolution_beats_name_matching_and_is_case_insensitive(): void
    {
        $this->trackedProduct('Alpha Widget', 100, 80, ['asin' => 'B00AAA1110']);
        // A decoy whose NAME contains Alpha's ASIN — an asin lookup must not see it.
        $this->trackedProduct('B00AAA1110 Lookalike Widget', 100, 80, ['asin' => 'B00BBB2220']);

        $payload = $this->toolPayload($this->callTool('get_price_history', ['asin' => 'b00aaa1110']));

        $this->assertSame('Alpha Widget', $payload['product']['name']);
    }

    public function test_query_resolves_review_slug_first_then_name_substring(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80);
        $this->trackedProduct('Widget Pro Max', 200, 150);

        $bySlug = $this->toolPayload($this->callTool('get_price_history', ['query' => 'widget-pro-max-review']));
        $this->assertSame('Widget Pro Max', $bySlug['product']['name']);

        $byName = $this->toolPayload($this->callTool('get_price_history', ['query' => 'pro max']));
        $this->assertSame('Widget Pro Max', $byName['product']['name']);
    }

    public function test_exactly_one_identifier_is_required(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80, ['asin' => 'B00DROP001']);

        foreach ([
            ['asin' => 'B00DROP001', 'query' => 'Dropped Widget'],
            [],
        ] as $arguments) {
            $response = $this->callTool('get_price_history', $arguments);

            $response->assertOk();
            $this->assertTrue((bool) $response->json('result.isError'));
            $this->assertStringContainsString('exactly one', (string) $response->json('result.content.0.text'));
        }
    }

    public function test_unknown_product_error_points_at_the_search_tool(): void
    {
        $response = $this->callTool('get_price_history', ['query' => 'Nonexistent Thing']);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('result.isError'));
        $this->assertStringContainsString('search_tracked_products', (string) $response->json('result.content.0.text'));
    }

    public function test_gated_product_returns_null_stats_with_the_reason(): void
    {
        $this->gatedProduct('Untracked Widget');

        $payload = $this->toolPayload($this->callTool('get_price_history', ['asin' => 'B00GATED01']));

        $this->assertFalse($payload['has_stats']);
        $this->assertNull($payload['stats']);
        $this->assertNull($payload['verdict']);
        $this->assertStringContainsString('insufficient history', $payload['stats_unavailable_reason']);
        $this->assertStringContainsString('14 days', $payload['stats_unavailable_reason']);
        // The minimum-truth rule: checked_at is always part of the payload.
        $this->assertArrayHasKey('checked_at', $payload);
        $this->assertEquals(50.0, $payload['current']);
    }

    public function test_draft_review_product_is_not_resolvable(): void
    {
        $product = $this->trackedProduct('Hidden Widget', 100, 80, ['asin' => 'B00HIDE001']);
        $product->posts()->first()->update(['status' => 'draft']);

        $response = $this->callTool('get_price_history', ['asin' => 'B00HIDE001']);

        $this->assertTrue((bool) $response->json('result.isError'));
    }

    public function test_deal_verdict_sentence_contains_price_pct_and_recency(): void
    {
        // Held at 100, dipped to 70 ten days ago, recovered to 80: well below
        // the 90-day average but not the record low → the 'good' tier and its
        // "% below" sentence (a plain 100→80 would be 'lowest').
        $product = $this->trackedProduct('Dropped Widget', 100, 80, ['asin' => 'B00DROP001']);
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => 70,
            'source' => 'manual',
            'created_at' => now()->subDays(10),
        ]);
        PriceIntel::flush($product->id);

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00DROP001']));

        $this->assertSame('good', $payload['verdict']);
        $this->assertStringContainsString('$80.00', $payload['sentence']);
        $this->assertStringContainsString('% below', $payload['sentence']);
        $this->assertStringContainsString('ago', $payload['sentence']);
        $this->assertStringContainsString('/out/', $payload['affiliate_url']);
    }

    public function test_deal_verdict_sentence_for_the_lowest_tier(): void
    {
        // Plain 100 → 80: today's price IS the varied 90-day low.
        $this->trackedProduct('Floor Widget', 100, 80, ['asin' => 'B00FLOOR01']);

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00FLOOR01']));

        $this->assertSame('lowest', $payload['verdict']);
        $this->assertStringContainsString('lowest price in our 90-day tracking', $payload['sentence']);
        $this->assertStringContainsString('$80.00', $payload['sentence']);
        $this->assertStringContainsString('ago', $payload['sentence']);
    }

    public function test_deal_verdict_sentence_for_a_flat_price_is_typical_never_lowest(): void
    {
        // Held at 100 for 60 days, never moved: gates open (2 snapshots, 60-day
        // span) but zero variation — "typical", per PriceIntel's honesty rule.
        $this->trackedProduct('Steady Widget', 100, 100, ['asin' => 'B00FLAT001']);

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00FLAT001']));

        $this->assertSame('typical', $payload['verdict']);
        $this->assertStringContainsString('in line with', $payload['sentence']);
        $this->assertStringContainsString('typical price', $payload['sentence']);
        $this->assertStringContainsString('ago', $payload['sentence']);
    }

    public function test_deal_verdict_sentence_for_an_elevated_price(): void
    {
        // Held at 100 for 60 days, jumped to 120 today: ~20% above the 90-day
        // average — the elevated tier's own pct arithmetic.
        $this->trackedProduct('Spiked Widget', 100, 120, ['asin' => 'B00SPIKE01']);

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00SPIKE01']));

        $this->assertSame('elevated', $payload['verdict']);
        $this->assertStringContainsString('% above', $payload['sentence']);
        $this->assertStringContainsString('$120.00', $payload['sentence']);
        $this->assertStringContainsString('waiting', $payload['sentence']);
        $this->assertStringContainsString('ago', $payload['sentence']);
    }

    public function test_deal_verdict_for_a_priceless_product_says_so(): void
    {
        // A reviewed product with no price on record at all.
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Priceless Widget',
            'asin' => 'B00NOPRICE',
            'affiliate_url' => 'https://www.amazon.com/dp/B00NOPRICE',
            'price' => null,
        ]);
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Priceless Widget Review',
            'slug' => 'priceless-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00NOPRICE']));

        $this->assertStringContainsString('no price on record', $payload['sentence']);
        $this->assertNull($payload['current']);
        $this->assertArrayHasKey('checked_at', $payload);
    }

    public function test_deal_verdict_for_gated_product_states_the_gate(): void
    {
        $this->gatedProduct('Untracked Widget');

        $payload = $this->toolPayload($this->callTool('get_deal_verdict', ['asin' => 'B00GATED01']));

        $this->assertNull($payload['verdict']);
        $this->assertStringContainsString('enough history', $payload['sentence']);
        $this->assertStringContainsString('$50.00', $payload['sentence']);
    }

    public function test_list_tracked_deals_respects_honesty_gates_and_limit(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80);
        $this->trackedProduct('Slashed Widget', 200, 150);
        $this->trackedProduct('Barely Dipped Widget', 100, 98); // ~2% — under the 5% bar
        $this->gatedProduct('Untracked Widget');                // gates closed

        $payload = $this->toolPayload($this->callTool('list_tracked_deals'));

        $this->assertSame(2, $payload['count']);
        $names = array_column($payload['deals'], 'name');
        $this->assertContains('Dropped Widget', $names);
        $this->assertContains('Slashed Widget', $names);
        $this->assertNotContains('Barely Dipped Widget', $names);
        $this->assertNotContains('Untracked Widget', $names);

        foreach ($payload['deals'] as $deal) {
            $this->assertStringContainsString('/out/', $deal['affiliate_url']);
            $this->assertStringContainsString('/posts/', $deal['review_url']);
            $this->assertNotNull($deal['checked_at']);
        }
        $this->assertStringContainsString('commission', $payload['disclosure']);

        $limited = $this->toolPayload($this->callTool('list_tracked_deals', ['limit' => 1]));
        $this->assertSame(1, $limited['count']);
        $this->assertCount(1, $limited['deals']);
    }

    public function test_empty_deals_feed_is_a_truthful_result_not_an_error(): void
    {
        $response = $this->callTool('list_tracked_deals');
        $payload = $this->toolPayload($response);

        $this->assertFalse((bool) $response->json('result.isError'));
        $this->assertSame(0, $payload['count']);
        $this->assertSame([], $payload['deals']);
    }
}
