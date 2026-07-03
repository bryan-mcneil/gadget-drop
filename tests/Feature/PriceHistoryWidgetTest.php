<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceHistoryWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeReviewWithProduct(float $price = 100): array
    {
        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'Tracked Widget',
            'asin'          => 'B00TRACK',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TRACK',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => $price,
            'description'   => 'A widget.',
        ]);

        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Tracked Widget Review',
            'slug'         => 'tracked-widget-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
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
            'price'      => $price,
            'source'     => 'manual',
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
}
