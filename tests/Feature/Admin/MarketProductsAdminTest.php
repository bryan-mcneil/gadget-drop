<?php

namespace Tests\Feature\Admin;

use App\Models\MarketPriceSnapshot;
use App\Models\MarketProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MarketProductsAdminTest extends TestCase
{
    use RefreshDatabase;

    private function marketProduct(array $overrides = []): MarketProduct
    {
        static $i = 0;
        $i++;

        return MarketProduct::create(array_merge([
            'asin' => sprintf('B0MARKET%02d', $i),
            'title' => "Market Widget {$i}",
            'brand' => 'Acme',
            'category' => 'Chargers',
            'current_price' => 49.99,
            'first_seen_at' => now()->subDays(10),
            'last_seen_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_the_screens_require_auth(): void
    {
        $product = $this->marketProduct();

        $this->get('/admin/market-products')->assertRedirect('/login');
        $this->get("/admin/market-products/{$product->id}/edit")->assertRedirect('/login');
        $this->put("/admin/market-products/{$product->id}", [])->assertRedirect('/login');
    }

    public function test_index_lists_products_most_recently_seen_first(): void
    {
        $older = $this->marketProduct(['last_seen_at' => now()->subDays(5)]);
        $newer = $this->marketProduct(['last_seen_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/market-products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MarketProducts/Index')
                ->has('products.data', 2)
                ->where('products.data.0.id', $newer->id)
                ->where('products.data.1.id', $older->id));
    }

    public function test_index_search_matches_title_and_exact_asin(): void
    {
        $this->marketProduct(['title' => 'USB-C Wall Charger']);
        $target = $this->marketProduct(['title' => 'Noise Cancelling Headphones', 'asin' => 'B0HEADPHON']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/market-products?search=headphones')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $target->id)
                ->where('filters.search', 'headphones'));

        // ASIN matches are exact (case-insensitive via uppercase normalization).
        $this->actingAs($user)
            ->get('/admin/market-products?search=b0headphon')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $target->id));
    }

    public function test_index_filters_by_category_and_lists_distinct_categories(): void
    {
        $this->marketProduct(['category' => 'Chargers']);
        $this->marketProduct(['category' => 'Chargers']);
        $audio = $this->marketProduct(['category' => 'Audio']);
        $this->marketProduct(['category' => null]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/market-products?category=Audio')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $audio->id)
                ->where('categories', ['Audio', 'Chargers']));
    }

    public function test_edit_screen_shows_product_and_change_only_snapshots(): void
    {
        $product = $this->marketProduct();
        MarketPriceSnapshot::create([
            'market_product_id' => $product->id,
            'price' => 59.99,
            'created_at' => now()->subDays(9),
        ]);
        MarketPriceSnapshot::create([
            'market_product_id' => $product->id,
            'price' => 49.99,
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/admin/market-products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MarketProducts/Edit')
                ->where('product.id', $product->id)
                ->where('product.asin', $product->asin)
                ->has('snapshots', 2)
                ->where('snapshots.0.price', '49.99'));
    }

    public function test_update_persists_curated_fields(): void
    {
        $product = $this->marketProduct();

        $this->actingAs(User::factory()->create())
            ->put("/admin/market-products/{$product->id}", [
                'title' => 'Cleaned Up Title',
                'description' => 'A tidy description.',
                'brand' => 'Anker',
                'category' => 'Wall Chargers',
                'image_url' => 'https://gadgetdrop.tech/storage/uploads/charger.webp',
            ])
            ->assertRedirect('/admin/market-products')
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('Cleaned Up Title', $product->title);
        $this->assertSame('A tidy description.', $product->description);
        $this->assertSame('Anker', $product->brand);
        $this->assertSame('Wall Chargers', $product->category);
        $this->assertSame('https://gadgetdrop.tech/storage/uploads/charger.webp', $product->image_url);
    }

    public function test_update_cannot_touch_import_owned_fields(): void
    {
        $product = $this->marketProduct(['current_price' => 49.99]);
        $originalAsin = $product->asin;

        $this->actingAs(User::factory()->create())
            ->put("/admin/market-products/{$product->id}", [
                'title' => 'Still Fine',
                'asin' => 'B0HACKED00',
                'current_price' => 1.00,
            ])
            ->assertRedirect('/admin/market-products');

        $product->refresh();
        $this->assertSame($originalAsin, $product->asin);
        $this->assertSame('49.99', (string) $product->current_price);
    }

    public function test_update_validates_title_and_image_url(): void
    {
        $product = $this->marketProduct();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/admin/market-products/{$product->id}", ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->put("/admin/market-products/{$product->id}", [
                'title' => 'Fine',
                'image_url' => 'not-a-url',
            ])
            ->assertSessionHasErrors('image_url');
    }
}
