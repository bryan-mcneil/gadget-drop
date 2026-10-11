<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PriceHistoryWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeReviewWithProduct(float $price = 100): array
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Tracked Widget',
            'asin' => 'B00TRACK',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TRACK',
            'image_url' => 'https://example.com/img.jpg',
            'price' => $price,
            'description' => 'A widget.',
        ]);

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Tracked Widget Review',
            'slug' => 'tracked-widget-review',
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return [$post, $product];
    }

    /** Add a historical snapshot without touching products.price. */
    private function snapshot(Product $product, float $price, int $daysAgo): void
    {
        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price' => $price,
            'source' => 'manual',
            'created_at' => now()->subDays($daysAgo),
        ]);
        PriceIntel::flush($product->id);
    }

    public function test_review_shows_the_checked_date_even_without_history(): void
    {
        [$post] = $this->makeReviewWithProduct();

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Tracked price', false)
            ->assertSee('Price checked', false)
            ->assertDontSee('90-day low', false);
    }

    public function test_review_shows_trend_stats_once_history_is_deep_enough(): void
    {
        [$post, $product] = $this->makeReviewWithProduct(90);

        // Held at 110 for weeks, dipped to 80, now 90: below the 90-day average
        // but not the record low → the "Below typical price" tier.
        $this->snapshot($product, 110, 40);
        $this->snapshot($product, 80, 20);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('90-day low', false)
            ->assertSee('Below typical price', false);
    }

    public function test_a_single_drop_with_enough_span_unlocks_stats(): void
    {
        // The common real-world shape: tracked at one price for weeks, then a
        // manual check records a drop. Two snapshots + ≥14-day span qualifies;
        // the current price is the lowest the tracker has seen.
        [$post, $product] = $this->makeReviewWithProduct(149);
        $this->snapshot($product, 179, 24);

        $stats = PriceIntel::stats($product->id);
        $this->assertTrue($stats['has_stats']);
        $this->assertSame('lowest', $stats['verdict']);
        $this->assertGreaterThan(5, $stats['drop_pct']);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Lowest tracked price', false);
    }

    public function test_stats_stay_hidden_below_the_honesty_thresholds(): void
    {
        [$post, $product] = $this->makeReviewWithProduct(90);

        // Three points but only 5 days of history — not enough span.
        $this->snapshot($product, 110, 5);
        $this->snapshot($product, 100, 2);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Price checked', false)
            ->assertDontSee('90-day low', false);
    }

    public function test_verdict_math(): void
    {
        [, $product] = $this->makeReviewWithProduct(100);
        $this->snapshot($product, 100, 60);
        $this->snapshot($product, 100, 30);

        // Held at 100 the whole window and still is → typical.
        $this->assertSame('typical', PriceIntel::stats($product->id)['verdict']);

        // Now the price drops to the lowest the tracker has ever seen.
        $product->update(['price' => 79]);
        PriceIntel::flush($product->id);

        $stats = PriceIntel::stats($product->id);
        $this->assertSame('lowest', $stats['verdict']);
        $this->assertNotNull($stats['drop_pct']);
    }

    public function test_elevated_price_is_called_out(): void
    {
        [, $product] = $this->makeReviewWithProduct(100);
        $this->snapshot($product, 100, 60);
        $this->snapshot($product, 100, 30);

        $product->update(['price' => 130]);
        PriceIntel::flush($product->id);

        $this->assertSame('elevated', PriceIntel::stats($product->id)['verdict']);
    }

    public function test_tip_and_news_type_posts_skip_price_intel(): void
    {
        // tech_tip exercises the same skip branch as tech_news (the sqlite test
        // schema's type CHECK predates tech_news — that enum widening is a
        // guarded MySQL-only migration).
        [$post] = $this->makeReviewWithProduct();
        $post->update(['type' => 'tech_tip']);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Tracked price', false);
    }

    public function test_sparkline_points_are_valid_svg_pairs(): void
    {
        $points = PriceIntel::sparklinePoints([
            ['2026-06-01', 100.0],
            ['2026-06-15', 80.0],
            ['2026-06-30', 90.0],
        ]);

        $this->assertMatchesRegularExpression('/^[\d.]+,[\d.]+( [\d.]+,[\d.]+)+$/', $points);
    }

    /**
     * The extracted <x-verdict-badge> is the single source of verdict language.
     * Each tier must render its label; this locks the copy against drift.
     */
    #[DataProvider('verdictTiers')]
    public function test_verdict_badge_renders_each_tier(string $verdict, string $label): void
    {
        $this->blade('<x-verdict-badge :verdict="$verdict" />', ['verdict' => $verdict])
            ->assertSee($label, false);
    }

    public static function verdictTiers(): array
    {
        return [
            'lowest' => ['lowest', 'Lowest tracked price'],
            'good' => ['good', 'Below typical price'],
            'typical' => ['typical', 'Typical price'],
            'elevated' => ['elevated', 'Higher than usual'],
        ];
    }

    public function test_verdict_badge_renders_nothing_for_null_or_unknown_verdict(): void
    {
        // ring-inset is unique to the badge pill — its absence proves no span rendered.
        $this->blade('<x-verdict-badge :verdict="$verdict" />', ['verdict' => null])
            ->assertDontSee('ring-inset', false);

        $this->blade('<x-verdict-badge :verdict="$verdict" />', ['verdict' => 'bogus'])
            ->assertDontSee('ring-inset', false);
    }

    public function test_drop_pct_is_appended_only_for_the_deal_tiers(): void
    {
        // lowest + good append the magnitude (dropPct is pre-rounded by PriceIntel).
        $this->blade('<x-verdict-badge :verdict="$v" :drop-pct="$d" />', ['v' => 'lowest', 'd' => 7.0])
            ->assertSee('Lowest tracked price · 7% below typical', false);
        $this->blade('<x-verdict-badge :verdict="$v" :drop-pct="$d" />', ['v' => 'good', 'd' => 6.3])
            ->assertSee('Below typical price · 6.3% below typical', false);

        // typical + elevated never append, even when a drop figure is present.
        $this->blade('<x-verdict-badge :verdict="$v" :drop-pct="$d" />', ['v' => 'typical', 'd' => 3.0])
            ->assertSee('Typical price', false)
            ->assertDontSee('·', false);

        // Below the 1% floor: no append even on a deal tier.
        $this->blade('<x-verdict-badge :verdict="$v" :drop-pct="$d" />', ['v' => 'good', 'd' => 0.4])
            ->assertSee('Below typical price', false)
            ->assertDontSee('·', false);
    }

    public function test_thirty_day_reference_line_shows_the_low30_figure(): void
    {
        // Dip to 50 sixty days ago (inside the 90-day window, OUTSIDE the 30-day
        // one), 110 at 40 days, 80 at 20 days, now 90. So low90 = 50 but low30 =
        // 80 — divergent on purpose, so "$80.00" can only be the 30-day line
        // (not the 90-day-low span, which shows $50, nor the $90 current price).
        [$post, $product] = $this->makeReviewWithProduct(90);
        $this->snapshot($product, 50, 60);
        $this->snapshot($product, 110, 40);
        $this->snapshot($product, 80, 20);

        $stats = PriceIntel::stats($product->id);
        $this->assertTrue($stats['has_stats']);
        $this->assertSame(80.0, $stats['low30']);
        $this->assertSame(50.0, $stats['low90']); // guards the divergence the assertion below relies on

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Lowest price in the last 30 days', false)
            ->assertSee('$80.00', false);
    }

    public function test_thirty_day_reference_line_and_badge_are_absent_without_stats(): void
    {
        // Only the observer's initial snapshot — one point, no span → gates unpassed.
        [$post] = $this->makeReviewWithProduct(120);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('Price checked', false)                        // baseline truth still shows
            ->assertDontSee('Lowest price in the last 30 days', false) // reference line gated off
            ->assertDontSee('Lowest tracked price', false)             // verdict badge gated off
            ->assertDontSee('Typical price', false);
    }
}
