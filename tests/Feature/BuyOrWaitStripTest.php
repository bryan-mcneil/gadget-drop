<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\ReleaseCycle;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Plan 06 §Phase 6.4: the review-page timing strip. Both directions matter:
 * it must appear when a line genuinely maps, and it must stay absent otherwise.
 * A strip that guesses is worse than no strip.
 */
class BuyOrWaitStripTest extends TestCase
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
            'name' => 'AirPods Pro',
            'slug' => 'airpods-pro',
            'typical_month' => 9,
            'cadence_months' => 36,
            'last_release_name' => 'AirPods Pro 3',
            'last_release_at' => now()->subMonths(10)->toDateString(),
            'source_url' => 'https://example.com/press-release',
            'verified_at' => now()->subMonth()->toDateString(),
        ], $overrides));
    }

    /** A published review of one product, optionally with tracked price history. */
    private function review(string $productName, ?Category $category = null, string $type = 'article', bool $tracked = true): Post
    {
        static $i = 0;
        $i++;

        $category ??= Category::firstOrCreate(['slug' => 'audio'], ['name' => 'Audio']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $productName,
            'brand' => 'Apple',
            'asin' => "B00STRIP{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00STRIP',
            'price' => 249,
        ]);

        if ($tracked) {
            ProductPriceSnapshot::create([
                'product_id' => $product->id,
                'price' => 249,
                'source' => 'manual',
                'created_at' => now()->subDays(60),
            ]);
            $product->update(['price' => 199]);
            PriceIntel::flush($product->id);
        }

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "{$productName} Review",
            'slug' => Str::slug($productName).'-review-'.$i,
            'type' => $type,
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);
        $post->categories()->attach($category->id);

        return $post;
    }

    public function test_the_strip_renders_on_a_review_whose_product_maps_to_a_cycle(): void
    {
        $this->cycle();
        $post = $this->review('AirPods Pro 3');

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Buy or wait?', false)
            ->assertSee('On the AirPods Pro line', false)
            ->assertSee(route('buy-or-wait.show', 'airpods-pro'), false)
            ->assertSee('See the timing', false);
    }

    public function test_the_strip_is_absent_when_no_cycle_maps(): void
    {
        $this->cycle();
        $post = $this->review('Anker Soundcore Q30');

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Buy or wait?', false)
            ->assertDontSee(route('buy-or-wait.show', 'airpods-pro'), false);
    }

    public function test_the_strip_is_absent_with_no_cycles_at_all(): void
    {
        $post = $this->review('AirPods Pro 3');

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Buy or wait?', false);
    }

    public function test_an_ambiguous_category_produces_no_strip(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        $this->cycle(['slug' => 'ipad', 'name' => 'iPad', 'category_id' => $computers->id]);
        $this->cycle(['slug' => 'macbook-air', 'name' => 'MacBook Air', 'category_id' => $computers->id]);

        $post = $this->review('Anker 737 Power Bank', $computers);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Buy or wait?', false);
    }

    public function test_tips_never_get_the_strip(): void
    {
        $this->cycle();
        $post = $this->review('AirPods Pro 3', null, 'tech_tip');

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Buy or wait?', false);
    }

    public function test_the_strip_carries_no_affiliate_link_of_its_own(): void
    {
        $this->cycle();
        $post = $this->review('AirPods Pro 3');

        $html = $this->get("/posts/{$post->slug}")->assertOk()->getContent();

        // The product card is the ONE affiliate link on a review page; the strip
        // must not add a second. Count /out/ hrefs and expect exactly the card's.
        $outLinks = substr_count($html, 'href="'.route('affiliate.redirect', ['product' => 1]));
        $this->assertLessThanOrEqual(1, $outLinks);

        // And the strip's own anchor points at an internal buy-or-wait page.
        $this->assertStringContainsString(
            '<a href="'.route('buy-or-wait.show', 'airpods-pro').'" wire:navigate',
            $html,
        );
    }

    public function test_the_strip_reflects_the_verdict_the_engine_returned(): void
    {
        // A one-year line ten months in is late, and with no price history to
        // weigh in the verdict is a plain "wait".
        $this->cycle(['cadence_months' => 12]);
        $post = $this->review('AirPods Pro 3', null, 'article', tracked: false);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('a refresh is close', false);
    }

    public function test_a_late_cycle_at_a_record_low_shows_the_clearance_reading_not_a_wait(): void
    {
        // Same late cycle, but the tracked price is the lowest we have recorded:
        // the matrix hands that back as "either", and the strip must say so
        // rather than telling a reader to wait past a genuine floor.
        $this->cycle(['cadence_months' => 12]);
        $post = $this->review('AirPods Pro 3');

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('no strong signal', false)
            ->assertDontSee('a refresh is close', false);
    }

    public function test_a_review_without_price_history_still_gets_a_cycle_only_strip(): void
    {
        $this->cycle();
        $post = $this->review('AirPods Pro 3', null, 'article', tracked: false);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Buy or wait?', false);
    }
}
