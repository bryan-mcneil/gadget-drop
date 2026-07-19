<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PricesAdminTest extends TestCase
{
    use RefreshDatabase;

    private function productWithPublishedPost(array $overrides = []): Product
    {
        static $i = 0;
        $i++;

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);

        $product = Product::create(array_merge([
            'category_id' => $category->id,
            'name' => "Priced Widget {$i}",
            'asin' => "B00PRICE{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00PRICE',
            'price' => 100,
        ], $overrides));

        $post = Post::create([
            'user_id' => User::factory()->create()->id,
            'title' => "Priced Widget {$i} Review",
            'slug' => "priced-widget-{$i}-review",
            'type' => 'article',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->products()->attach($product->id, ['display_order' => 1]);

        return $product;
    }

    public function test_the_screen_requires_auth(): void
    {
        $this->get('/admin/prices')->assertRedirect('/login');
    }

    public function test_index_lists_products_stalest_first(): void
    {
        $fresh = $this->productWithPublishedPost(); // checked now (observer)
        $stale = $this->productWithPublishedPost();
        $stale->forceFill(['price_checked_at' => now()->subDays(20)])->saveQuietly();
        $never = $this->productWithPublishedPost();
        $never->forceFill(['price_checked_at' => null])->saveQuietly();

        $this->actingAs(User::factory()->create())
            ->get('/admin/prices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Prices/Index')
                ->where('products.data.0.id', $never->id)
                ->where('products.data.1.id', $stale->id)
                ->where('products.data.2.id', $fresh->id)
            );
    }

    public function test_updating_a_price_snapshots_it(): void
    {
        $product = $this->productWithPublishedPost(['price' => 100]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/prices/{$product->id}", ['price' => 89.99])
            ->assertRedirect();

        $this->assertSame('89.99', (string) $product->fresh()->price);
        $this->assertSame(2, $product->priceSnapshots()->count()); // create + update
    }

    public function test_resubmitting_the_same_price_counts_as_a_check_not_a_change(): void
    {
        $product = $this->productWithPublishedPost(['price' => 100]);
        $product->forceFill(['price_checked_at' => now()->subDays(10)])->saveQuietly();
        $product->priceSnapshots()->update(['created_at' => now()->subDays(10)]);

        $this->actingAs(User::factory()->create())
            ->post("/admin/prices/{$product->id}", ['price' => 100])
            ->assertRedirect();

        // Not a price change — but the check itself is recorded as a
        // same-price snapshot so it stays durable.
        $this->assertSame(2, $product->priceSnapshots()->count());
        $this->assertSame('100.00', (string) $product->priceSnapshots()->latest('id')->first()->price);
        $this->assertTrue($product->fresh()->price_checked_at->isToday());
    }

    public function test_saving_a_price_flashes_a_confirmation_the_ui_can_render(): void
    {
        $product = $this->productWithPublishedPost(['price' => 100]);

        $this->actingAs(User::factory()->create())
            ->from('/admin/prices')
            ->post("/admin/prices/{$product->id}", ['price' => 89.99]);

        // The banner in the React pages reads the shared `flash.success` prop;
        // it must survive the redirect back to the index.
        $this->get('/admin/prices')
            ->assertInertia(fn (Assert $page) => $page
                ->where('flash.success', "{$product->name}: price confirmed at \$89.99.")
            );
    }

    public function test_confirm_records_a_durable_same_price_check(): void
    {
        $product = $this->productWithPublishedPost(['price' => 100]);
        $product->forceFill(['price_checked_at' => now()->subDays(10)])->saveQuietly();
        $product->priceSnapshots()->update(['created_at' => now()->subDays(10)]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/admin/prices/{$product->id}/confirm")
            ->assertRedirect();

        $this->assertTrue($product->fresh()->price_checked_at->isToday());

        // The check is a snapshot row (Truth Report observation evidence +
        // PriceIntel cache flush), not just the mutable stamp.
        $this->assertSame(2, $product->priceSnapshots()->count());
        $latest = $product->priceSnapshots()->latest('id')->first();
        $this->assertSame('100.00', (string) $latest->price);
        $this->assertSame('manual', $latest->source);
        $this->assertTrue($latest->created_at->isToday());

        // A second confirm the same day moves the stamp, never pads history.
        $this->actingAs($user)
            ->post("/admin/prices/{$product->id}/confirm")
            ->assertRedirect();

        $this->assertSame(2, $product->priceSnapshots()->count());
    }
}
