<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LivePriceCompare;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceCompareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Plan 11 Phase 11.3. Never hits the real Anthropic API: PriceCompareService
 * is mocked at the container level (the SDK talks PSR-18, not Laravel's Http
 * facade, so Http::fake() cannot intercept it).
 *
 * Post type is `article` throughout, never `tech_news`: the sqlite test
 * schema's CHECK constraint predates that enum widening.
 */
class LivePriceCompareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('price-compare:127.0.0.1');

        config([
            'price-compare.enabled' => true,
            'services.claude.api_key' => 'test-key',
            'price-compare.retailers' => ['walmart.com', 'bestbuy.com', 'target.com'],
            'price-compare.fresh_days' => 7,
            'price-compare.confidence_floor' => 0.6,
        ]);
    }

    // ------------------------------------------------------------- the gates

    public function test_a_disabled_feature_renders_no_button_on_the_post_page(): void
    {
        config(['price-compare.enabled' => false]);

        $post = $this->publishedPost($this->product());

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertDontSee('Compare prices at other retailers');
    }

    public function test_the_button_renders_on_the_post_page_when_enabled(): void
    {
        $post = $this->publishedPost($this->product());

        $this->get(route('posts.show', $post->slug))
            ->assertOk()
            ->assertSee('Compare prices at other retailers');
    }

    public function test_a_product_with_no_tracked_price_is_gated_and_never_calls_the_service(): void
    {
        $this->mockService(expectCalls: 0);

        Livewire::test(LivePriceCompare::class, ['productId' => $this->product(['price' => null])->id])
            ->assertSet('phase', 'gated')
            ->call('compare')
            ->assertSet('phase', 'gated');
    }

    // ------------------------------------------------------------ the result

    public function test_a_successful_lookup_renders_the_retailers_and_prices(): void
    {
        $this->mockService($this->payload(), expectCalls: 1);

        Livewire::test(LivePriceCompare::class, ['productId' => $this->product()->id])
            ->call('compare')
            ->assertSet('phase', 'ready')
            ->assertSee('Walmart')
            ->assertSee('$189.99')
            ->assertSee('Our tracked Amazon price');
    }

    public function test_a_second_click_is_served_from_cache_without_a_second_call(): void
    {
        // ->once() on the mock is the assertion: a second call fails the test.
        $this->mockService($this->payload(), expectCalls: 1);

        $product = $this->product();

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])->call('compare');
        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'ready');
    }

    public function test_changing_our_price_busts_the_cache_key(): void
    {
        $this->mockService($this->payload(), expectCalls: 2);

        $product = $this->product();

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])->call('compare');

        $product->update(['price' => 249.99]);

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'ready');
    }

    public function test_an_empty_result_is_a_first_class_finding_and_is_cached(): void
    {
        $this->mockService($this->payload(results: []), expectCalls: 1);

        $product = $this->product();

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'empty')
            ->assertSee('Amazon-exclusive');

        // Cached like any other real answer: an Amazon-exclusive product must
        // not re-burn budget on every click forever.
        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'empty');
    }

    public function test_a_low_confidence_payload_declines_to_show_prices(): void
    {
        $this->mockService($this->payload(confidence: 0.3), expectCalls: 1);

        Livewire::test(LivePriceCompare::class, ['productId' => $this->product()->id])
            ->call('compare')
            ->assertSet('phase', 'unavailable')
            ->assertSee("couldn't confidently match this exact model", false)
            ->assertDontSee('$189.99');
    }

    public function test_a_failure_is_not_cached_so_a_retry_can_succeed(): void
    {
        $this->mockService(null, expectCalls: 2);

        $product = $this->product();

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'failed');

        // A second attempt must reach the service again: nothing was written.
        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'failed');
    }

    // ------------------------------------------------------------ the honesty

    /**
     * THE BIAS GUARD. Competitor prices came back live; ours is whatever we
     * last recorded. Past the freshness window the table still renders and the
     * verdict does not, because the error that matters is wrongly crowning
     * Amazon.
     */
    public function test_a_stale_tracked_price_renders_the_rows_but_names_no_winner(): void
    {
        $this->mockService($this->payload(), expectCalls: 1);

        $product = $this->product(['price_checked_at' => now()->subDays(30)]);

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'ready')
            ->assertSet('result.winner', null)
            ->assertSet('result.winner_label', null)
            ->assertSee('$189.99')
            ->assertSee("we're not going to call a winner", false)
            ->assertDontSee('has the lowest in-stock price');
    }

    public function test_a_fresh_tracked_price_does_name_a_winner(): void
    {
        $this->mockService($this->payload(), expectCalls: 1);

        $product = $this->product(['price' => 219.99, 'price_checked_at' => now()->subDay()]);

        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('result.winner', 'retailer')
            ->assertSee('has the lowest in-stock price');
    }

    // ----------------------------------------------------------- the throttle

    public function test_the_uncached_path_is_throttled_but_a_cache_hit_is_not(): void
    {
        $this->mockService($this->payload(), expectCalls: 1);

        $product = $this->product();

        // Prime the cache with one real (allowed) lookup.
        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])->call('compare');

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('price-compare:127.0.0.1', 3600);
        }

        // Cached: costs nothing, so it must not be throttled.
        Livewire::test(LivePriceCompare::class, ['productId' => $product->id])
            ->call('compare')
            ->assertSet('phase', 'ready');

        // Uncached (different product): throttled, and the service is never
        // reached, which the mock's call count enforces.
        Livewire::test(LivePriceCompare::class, ['productId' => $this->product(['asin' => 'B0OTHER'])->id])
            ->call('compare')
            ->assertSet('phase', 'throttled');
    }

    // ---------------------------------------------------------- the tampering

    /**
     * Livewire applies the client's `updates` array after validating the
     * snapshot checksum, so an UNLOCKED public property is client-writable.
     * Without Locked on $phase/$result, a crafted update sets phase=ready and
     * hands rows straight to the view, bypassing PriceComparison entirely,
     * including its URL scheme check.
     */
    public function test_no_public_property_accepts_a_client_update(): void
    {
        $component = Livewire::test(LivePriceCompare::class, ['productId' => $this->product()->id]);

        foreach ([
            'productId' => 999,
            'phase' => 'ready',
            'result' => ['rows' => [['retailer' => 'Walmart', 'price' => 1.0, 'url' => 'javascript:alert(1)', 'in_stock' => true]]],
        ] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("\${$property} accepted a client update: it needs #[Locked].");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
    }

    // ---------------------------------------------------------- the budget

    public function test_an_exhausted_daily_budget_says_so_rather_than_inviting_a_retry(): void
    {
        // "Try again in a moment" would be false: the budget resets at midnight.
        $this->mock(PriceCompareService::class, function ($mock) {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('requestsRemainingToday')->andReturn(0);
            $mock->shouldNotReceive('compare');
        });

        Livewire::test(LivePriceCompare::class, ['productId' => $this->product()->id])
            ->call('compare')
            ->assertSet('phase', 'exhausted')
            ->assertSee('Try again tomorrow')
            ->assertDontSee('Try again in a moment');
    }

    // ---------------------------------------------------------- the commerce

    public function test_the_widget_adds_no_affiliate_cta_and_no_amazon_link(): void
    {
        $this->mockService($this->payload(), expectCalls: 1);

        $html = Livewire::test(LivePriceCompare::class, ['productId' => $this->product()->id])
            ->call('compare')
            ->html();

        $this->assertStringNotContainsString('amazon.com', $html, 'The comparison must never link to Amazon.');
        $this->assertStringNotContainsString('Check Current Prices', $html);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $html);
    }

    /**
     * The SEO guarantee: the widget only fills in after a click, so the
     * server-rendered HTML a crawler sees carries no third-party prices and no
     * outbound retailer links.
     */
    public function test_the_server_rendered_page_carries_no_third_party_prices(): void
    {
        $this->mockService(expectCalls: 0);

        $post = $this->publishedPost($this->product());

        $response = $this->get(route('posts.show', $post->slug))->assertOk();

        $response->assertDontSee('Walmart');
        $response->assertDontSee('$189.99');
        $response->assertDontSee('walmart.com');
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function mockService(?array $payload = null, int $expectCalls = 1): void
    {
        $this->mock(PriceCompareService::class, function ($mock) use ($payload, $expectCalls) {
            $mock->shouldReceive('isEnabled')->andReturn((bool) config('price-compare.enabled'));
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('requestsRemainingToday')->andReturn(150);

            if ($expectCalls === 0) {
                $mock->shouldNotReceive('compare');

                return;
            }

            $mock->shouldReceive('compare')->times($expectCalls)->andReturn($payload);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $results
     * @return array<string, mixed>
     */
    private function payload(?array $results = null, float $confidence = 0.9): array
    {
        return [
            'confidence' => $confidence,
            'retailers_checked' => 3,
            'results' => $results ?? [[
                'retailer' => 'Walmart',
                'price' => 189.99,
                'url' => 'https://www.walmart.com/ip/12345',
                'in_stock' => true,
                'exact_model_match' => true,
            ]],
        ];
    }

    /** @param  array<string, mixed>  $overrides */
    private function product(array $overrides = []): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'gadgets'],
            ['name' => 'Gadgets', 'description' => 'Test category.'],
        );

        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => 'UGREEN NASync DXP2800',
            'brand' => 'UGREEN',
            'asin' => 'B0TESTASIN',
            'affiliate_url' => 'https://www.amazon.com/dp/B0TESTASIN',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 199.99,
            'price_checked_at' => now()->subDay(),
            'description' => 'A test gadget.',
        ], $overrides));
    }

    private function publishedPost(Product $product): Post
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => $product->name.' Review',
            'slug' => 'ugreen-nasync-review-'.uniqid(),
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $post->products()->attach($product->id, ['display_order' => 1]);
        $post->categories()->attach($product->category_id);

        return $post;
    }
}
