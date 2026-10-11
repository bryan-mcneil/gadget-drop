<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\MarketPriceSnapshot;
use App\Models\MarketProduct;
use App\Models\Product;
use App\Models\ProductPriceSnapshot;
use App\Models\User;
use App\Support\PriceIntel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Promoting a market-layer row into the curated catalog
 * (docs/plans/08-market-promote.md): product created from market data, price
 * history seeded with source='market' at the original observation dates, no
 * observer "observed today" snapshot, duplicate ASINs blocked.
 */
class MarketPromotionTest extends TestCase
{
    use RefreshDatabase;

    private function marketProduct(array $overrides = []): MarketProduct
    {
        static $i = 0;
        $i++;

        return MarketProduct::create(array_merge([
            'asin' => sprintf('B0PROMOTE%01d', $i),
            'title' => "Promotable Widget {$i}",
            'description' => 'A very promotable widget.',
            'brand' => 'Acme',
            'category' => 'Chargers',
            'image_url' => 'https://gadgetdrop.tech/storage/uploads/widget.webp',
            'current_price' => 49.99,
            'rating' => 4.5,
            'review_count' => 1234,
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now()->subDay(),
        ], $overrides));
    }

    private function snapshot(MarketProduct $product, float $price, int $daysAgo): MarketPriceSnapshot
    {
        return MarketPriceSnapshot::create([
            'market_product_id' => $product->id,
            'price' => $price,
            'created_at' => now()->subDays($daysAgo),
        ]);
    }

    public function test_promote_requires_auth(): void
    {
        $product = $this->marketProduct();

        $this->post("/admin/market-products/{$product->id}/promote", [])->assertRedirect('/login');
    }

    public function test_promote_creates_catalog_product_with_seeded_history(): void
    {
        $market = $this->marketProduct();
        $this->snapshot($market, 59.99, 30);
        $this->snapshot($market, 49.99, 10);
        $category = Category::create(['name' => 'Chargers & Power', 'slug' => 'chargers-power']);

        $response = $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", ['category_id' => $category->id]);

        $product = Product::where('asin', $market->asin)->first();
        $this->assertNotNull($product);
        $response->assertRedirect("/admin/products/{$product->id}/edit");
        $response->assertSessionHas('success');

        $this->assertSame($market->title, $product->name);
        $this->assertSame('Acme', $product->brand);
        $this->assertSame("https://www.amazon.com/dp/{$market->asin}", $product->affiliate_url);
        $this->assertSame($market->image_url, $product->image_url);
        $this->assertSame('49.99', (string) $product->price);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('A very promotable widget.', $product->description);
        $this->assertSame('4.5', (string) $product->amazon_rating);
        $this->assertSame(1234, $product->amazon_review_count);

        // The "Price checked" stamp is the market's last sighting, not the
        // moment someone clicked Promote.
        $this->assertSame(
            $market->last_seen_at->toDateTimeString(),
            $product->price_checked_at->toDateTimeString(),
        );

        // Exactly the market history — source, dates, order — and nothing
        // else (the observer's created-snapshot is deliberately suppressed).
        $snapshots = $product->priceSnapshots()->orderBy('created_at')->get();
        $this->assertCount(2, $snapshots);
        $this->assertSame(['market', 'market'], $snapshots->pluck('source')->all());
        $this->assertSame(['59.99', '49.99'], $snapshots->pluck('price')->map(fn ($p) => (string) $p)->all());
        $this->assertTrue($snapshots[0]->created_at->isSameDay(now()->subDays(30)));
        $this->assertTrue($snapshots[1]->created_at->isSameDay(now()->subDays(10)));
    }

    public function test_promote_is_blocked_when_asin_is_already_in_the_catalog(): void
    {
        $market = $this->marketProduct();
        $this->snapshot($market, 49.99, 5);
        // No price → no observer snapshot, so any snapshot below is a leak.
        Product::create([
            'name' => 'Already Curated',
            'asin' => $market->asin,
            'affiliate_url' => "https://www.amazon.com/dp/{$market->asin}",
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", [])
            ->assertRedirect("/admin/market-products/{$market->id}/edit")
            ->assertSessionHasErrors('promote');

        $this->assertSame(1, Product::count());
        $this->assertSame(0, ProductPriceSnapshot::count());
    }

    public function test_promote_without_market_snapshots_seeds_one_fallback_row(): void
    {
        $market = $this->marketProduct();

        $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", []);

        $product = Product::where('asin', $market->asin)->first();
        $snapshots = $product->priceSnapshots()->get();

        $this->assertCount(1, $snapshots);
        $this->assertSame('market', $snapshots[0]->source);
        $this->assertSame('49.99', (string) $snapshots[0]->price);
        $this->assertSame(
            $market->last_seen_at->toDateTimeString(),
            $snapshots[0]->created_at->toDateTimeString(),
        );
        $this->assertNull($product->category_id);
    }

    public function test_seeded_history_opens_the_price_intel_gates_immediately(): void
    {
        $market = $this->marketProduct();
        $this->snapshot($market, 59.99, 30);
        $this->snapshot($market, 49.99, 10);

        $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", []);

        $product = Product::where('asin', $market->asin)->first();
        $stats = PriceIntel::stats($product->id);

        // ≥2 points spanning ≥14 days — the whole point of seeding: verdict
        // and sparkline are live before the review even publishes.
        $this->assertTrue($stats['has_stats']);
        $this->assertNotNull($stats['verdict']);
        $this->assertSame(49.99, $stats['low90']);
    }

    public function test_promote_maps_overflowing_columns_safely(): void
    {
        // Market columns are wider than their products counterparts: title
        // 500→name 255 (truncate), image_url 500→255 (drop — a cut URL 404s),
        // review_count unsignedInteger→unsignedMediumInteger (cap).
        $market = $this->marketProduct([
            'title' => str_repeat('T', 300),
            'image_url' => 'https://gadgetdrop.tech/storage/uploads/'.str_repeat('x', 260).'.webp',
            'review_count' => 20_000_000,
        ]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", []);

        $product = Product::where('asin', $market->asin)->first();
        $this->assertSame(str_repeat('T', 255), $product->name);
        $this->assertNull($product->image_url);
        $this->assertSame(16_777_215, $product->amazon_review_count);
    }

    public function test_promote_validates_category_id(): void
    {
        $market = $this->marketProduct();

        $this->actingAs(User::factory()->create())
            ->post("/admin/market-products/{$market->id}/promote", ['category_id' => 999])
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Product::count());
    }

    public function test_edit_screen_exposes_promotion_state(): void
    {
        $market = $this->marketProduct();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get("/admin/market-products/{$market->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('curated', null)
                ->has('siteCategories'));

        $catalog = Product::create([
            'name' => 'Curated Twin',
            'asin' => $market->asin,
            'affiliate_url' => "https://www.amazon.com/dp/{$market->asin}",
        ]);

        $this->actingAs($user)
            ->get("/admin/market-products/{$market->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('curated.id', $catalog->id)
                ->where('curated.name', 'Curated Twin'));
    }

    public function test_index_flags_rows_already_in_the_catalog(): void
    {
        $promoted = $this->marketProduct(['last_seen_at' => now()]);
        $this->marketProduct(['last_seen_at' => now()->subHour()]);
        Product::create([
            'name' => 'Curated Twin',
            'asin' => $promoted->asin,
            'affiliate_url' => "https://www.amazon.com/dp/{$promoted->asin}",
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/market-products')
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.data.0.curated', true)
                ->where('products.data.1.curated', false));
    }
}
