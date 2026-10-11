<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DealsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A product with a published review and a controlled price history:
     * held at $historic for weeks, currently at $current.
     */
    private function trackedProduct(string $name, float $historic, float $current): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'asin' => "B00DEAL{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00DEAL',
            'image_url' => 'https://example.com/img.jpg',
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

    public function test_a_real_drop_appears_with_tracked_framing(): void
    {
        // 100 → 80: ~18% below the 90-day average once today's price is weighed in.
        $this->trackedProduct('Dropped Widget', 100, 80);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Dropped Widget', false)
            ->assertSee('usually $', false)
            ->assertSee('Read our take', false)
            ->assertSee('/out/', false);
    }

    public function test_a_small_dip_does_not_qualify(): void
    {
        // 100 → 98: ~2% below typical — under the 5% bar.
        $this->trackedProduct('Barely Dipped Widget', 100, 98);

        // The review title still shows in the header's Trending menu, so probe
        // for deal-card markup ("usually $…") rather than the product name.
        $this->get('/deals')
            ->assertOk()
            ->assertDontSee('usually $', false)
            ->assertSee('No qualifying drops right now', false);
    }

    public function test_a_product_without_enough_history_does_not_qualify(): void
    {
        // Only today's snapshot — honesty gates keep it out no matter the price.
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Untracked Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00NEW',
            'price' => 50,
        ]);
        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Untracked Widget Review',
            'slug' => 'untracked-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        $this->get('/deals')
            ->assertOk()
            ->assertDontSee('usually $', false)
            ->assertSee('No qualifying drops right now', false);
    }

    public function test_a_drop_without_a_published_review_does_not_qualify(): void
    {
        $product = $this->trackedProduct('Unlinked Widget', 100, 80);
        $product->posts()->first()->update(['status' => 'draft']);
        PriceIntel::flush($product->id);

        $this->get('/deals')
            ->assertOk()
            ->assertDontSee('Unlinked Widget', false);
    }

    public function test_the_page_is_indexable_with_methodology_prose(): void
    {
        $this->get('/deals')
            ->assertOk()
            ->assertDontSee('name="robots" content="noindex', false)
            ->assertSee('tracked 90-day average', false)
            // Zero tracked products -> the whole stat-chip row is absent.
            ->assertDontSee('products tracked', false)
            ->assertSee('rel="canonical" href="'.route('deals').'"', false);
    }

    public function test_deals_is_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('deals'), false);
    }

    public function test_deal_cards_show_the_shared_badge_and_the_30_day_low(): void
    {
        // Held at 100 for weeks, dipped to 70 ten days ago, recovered to 80
        // today: ~16% below the 90-day average (qualifies) but not the record
        // low → the 'good' tier. low30 = 70 — divergent from current (80) and
        // typical (~95), so "$70.00" can only be the 30-day-low span.
        $product = $this->trackedProduct('Recovered Widget', 100, 80);
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => 70,
            'source' => 'manual',
            'created_at' => now()->subDays(10),
        ]);
        PriceIntel::flush($product->id);

        $stats = PriceIntel::stats($product->id);
        $this->assertSame('good', $stats['verdict']);
        $this->assertSame(70.0, $stats['low30']);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Below typical price', false)      // shared badge label
            ->assertSee('text-[10px] px-2 py-0.5', false)  // the sm badge sizing string
            ->assertSee('30-day low', false)
            ->assertSee('$70.00', false);
    }

    public function test_the_feed_passes_low30_through(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80);

        $this->get('/deals')->assertOk();

        $feed = Cache::get('deals.feed');
        $this->assertIsArray($feed);
        $this->assertNotEmpty($feed);
        $this->assertArrayHasKey('low30', $feed[0]);
        $this->assertSame(80.0, $feed[0]['low30']);
    }

    public function test_the_lede_shows_honest_stat_chips_when_drops_exist(): void
    {
        $this->trackedProduct('Dropped Widget', 100, 80);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('products tracked', false)  // trackedCount chip
            ->assertSee('live now', false)          // liveCount chip
            ->assertSee('biggest drop', false);     // topDrop chip
    }

    public function test_the_lede_hides_the_live_chips_when_no_drops_qualify(): void
    {
        // Tracked (snapshots + a published review) but only a 2% dip: it counts
        // toward "products tracked" yet yields zero live drops, so the "live now"
        // and "biggest drop" chips must stay hidden.
        $this->trackedProduct('Barely Dipped Widget', 100, 98);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('products tracked', false)
            ->assertDontSee('live now', false)
            ->assertDontSee('biggest drop', false);
    }

    public function test_the_header_deals_pill_reflects_the_live_count(): void
    {
        // No qualifying deals yet → the Deals nav item shows no count pill.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('bg-sky-100 text-sky-700 text-[11px]', false);

        // A real drop → PriceIntel::flush busts the feed; the pill now renders.
        $this->trackedProduct('Dropped Widget', 100, 80);

        $this->get('/')
            ->assertOk()
            ->assertSee('bg-sky-100 text-sky-700 text-[11px]', false);
    }

    public function test_a_stale_cached_feed_without_low30_still_renders(): void
    {
        // Simulates the ≤1h post-deploy window: a cached feed in the pre-deploy
        // shape (no 'low30' key). The card must render, minus the 30-day line.
        Cache::put('deals.feed', [[
            'product_id' => 1,
            'name' => 'Stale Widget',
            'image_url' => null,
            'current' => 80.0,
            'typical' => 95.0,
            'low90' => 70.0,
            'drop_pct' => 15.8,
            'verdict' => 'good',
            'checked_at' => now()->subHours(3)->toDateTimeString(),
            'points' => [],
            'post_id' => 1,
            'post_title' => 'Stale Widget Review',
            'post_slug' => 'stale-widget-review',
            'worth_pct' => null,
            'worth_total' => 0,
        ]], 60);

        $this->get('/deals')
            ->assertOk()
            ->assertSee('Stale Widget', false)
            ->assertSee('Below typical price', false)
            ->assertDontSee('30-day low', false);
    }
}
