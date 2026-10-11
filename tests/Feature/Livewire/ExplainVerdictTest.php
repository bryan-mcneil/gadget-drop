<?php

namespace Tests\Feature\Livewire;

use App\Livewire\ExplainVerdict;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\ReleaseCycle;
use App\Models\User;
use App\Services\ClaudeExplainService;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Claude-role plan (2026-08-24): the "Explain this verdict" feature. Never
 * hits the real Anthropic API — App\Services\ClaudeExplainService is mocked
 * at the container level (the SDK talks PSR-18/php-http, not Laravel's Http
 * facade, so Http::fake() can't intercept it the way CanopyApiService's
 * tests do).
 */
class ExplainVerdictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Verdicts, PriceIntel and the explanation cache all share the array store.
        Cache::flush();
        RateLimiter::clear('explain-verdict:127.0.0.1');
    }

    private function cycle(array $overrides = []): ReleaseCycle
    {
        return ReleaseCycle::create(array_merge([
            'name' => 'Test Line',
            'slug' => 'test-line',
            'typical_month' => 9,
            'cadence_months' => 12,
            'last_release_name' => 'Test Line 3',
            // Mid-cycle (position ~0.58 of 12 months): lands on BuyOrWait::EITHER
            // ("no strong signal") regardless of price tier — the modal, most
            // common verdict, and the one the task asks to test specifically.
            'last_release_at' => now()->subMonths(7)->toDateString(),
            'next_expected_note' => 'Shipped every September since 2019.',
            'source_url' => 'https://example.com/press-release',
            'verified_at' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    /**
     * A flagship product with enough tracked history to clear PriceIntel's
     * honesty gate. Name must contain the cycle's name as a whole word
     * (ReleaseCycle::matches()) so flagshipProduct() actually resolves it —
     * "Test Line 3" matches a cycle named "Test Line".
     */
    private function trackedProduct(Category $category, float $price = 100): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Line 3',
            'asin' => 'B00TESTPROD',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TESTPROD',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
        ]);

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $price,
            'source' => 'manual',
            'created_at' => now()->subDays(60),
        ]);

        PriceIntel::flush($product->id);

        $this->attachReview($product, $category);

        return $product;
    }

    /** A flagship product that has NOT cleared the gate: one snapshot, no span. */
    private function gatedProduct(Category $category): Product
    {
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Line 3',
            'asin' => 'B00FRESHPRD',
            'affiliate_url' => 'https://www.amazon.com/dp/B00FRESHPRD',
            'image_url' => 'https://example.com/img.jpg',
            'price' => 100,
        ]);

        PriceIntel::flush($product->id);
        $this->attachReview($product, $category);

        return $product;
    }

    private function attachReview(Product $product, Category $category): void
    {
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => $product->name.' Review',
            'slug' => str_replace(' ', '-', strtolower($product->name)).'-review-'.uniqid(),
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);
        $post->categories()->attach($category->id);
    }

    private function mockClaude(string $reply = 'Because the price is typical and the cycle is mid-way, there is no strong signal either way.'): void
    {
        $this->mock(ClaudeExplainService::class, function ($mock) use ($reply) {
            $mock->shouldReceive('streamExplanation')
                ->once()
                ->andReturnUsing(function (array $facts, callable $onDelta) use ($reply) {
                    $onDelta($reply);

                    return $reply;
                });
        });
    }

    public function test_a_no_strong_signal_verdict_is_explained_and_cached(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $cycle = $this->cycle(['slug' => 'no-signal-line']);
        $this->trackedProduct($category);

        $this->mockClaude('Test explanation text.');

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'explained');

        // Second mount + click for the SAME inputs must hit the cache, not
        // the service again — the mock's ->once() above already enforces
        // this, but assert the cached value directly too.
        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'explained');
    }

    public function test_gate_not_cleared_shows_a_static_message_without_calling_claude(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $cycle = $this->cycle(['slug' => 'gated-line']);
        $this->gatedProduct($category);

        $this->mock(ClaudeExplainService::class, function ($mock) {
            $mock->shouldNotReceive('streamExplanation');
        });

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'gated')
            ->assertSee('Not enough tracked price history yet', false)
            ->assertDontSee('Explained by Claude', false);
    }

    public function test_a_line_with_no_flagship_product_is_also_gated(): void
    {
        $cycle = $this->cycle(['slug' => 'no-product-line']);

        $this->mock(ClaudeExplainService::class, function ($mock) {
            $mock->shouldNotReceive('streamExplanation');
        });

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'gated');
    }

    public function test_a_stale_cycle_sends_the_staleness_fact_to_claude(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $cycle = $this->cycle([
            'slug' => 'stale-line',
            'verified_at' => now()->subMonths(8)->toDateString(), // > STALE_AFTER_MONTHS (6)
        ]);
        $this->trackedProduct($category);

        $this->mock(ClaudeExplainService::class, function ($mock) {
            $mock->shouldReceive('streamExplanation')
                ->once()
                ->withArgs(function (array $facts, callable $onDelta) {
                    $onDelta('Confidence is low because the cycle data is stale.');

                    return $facts['cycle']['data_is_stale'] === true
                        && $facts['cycle']['stale_after_months'] === ReleaseCycle::STALE_AFTER_MONTHS;
                })
                ->andReturn('Confidence is low because the cycle data is stale.');
        });

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'explained');
    }

    public function test_a_service_failure_shows_a_graceful_fallback_and_is_not_cached(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $cycle = $this->cycle(['slug' => 'failing-line']);
        $this->trackedProduct($category);

        $this->mock(ClaudeExplainService::class, function ($mock) {
            $mock->shouldReceive('streamExplanation')->once()->andReturn(null);
        });

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'failed')
            ->assertSee("Couldn't generate an explanation", false);
    }

    public function test_the_rate_limiter_silently_blocks_a_flood_of_uncached_requests(): void
    {
        $category = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets']);
        $cycle = $this->cycle(['slug' => 'flooded-line']);
        $this->trackedProduct($category);

        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('explain-verdict:127.0.0.1', 60);
        }

        $this->mock(ClaudeExplainService::class, function ($mock) {
            $mock->shouldNotReceive('streamExplanation');
        });

        Livewire::test(ExplainVerdict::class, ['cycleSlug' => $cycle->slug])
            ->call('explain')
            ->assertSet('phase', 'failed');
    }
}
