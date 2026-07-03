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
            'category_id'   => $category->id,
            'name'          => $name,
            'asin'          => "B00DEAL{$i}",
            'affiliate_url' => 'https://www.amazon.com/dp/B00DEAL',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => $historic,
        ]);

        ProductPriceSnapshot::create([
            'product_id' => $product->id,
            'price'      => $historic,
            'source'     => 'manual',
            'created_at' => now()->subDays(60),
        ]);

        if ($current !== $historic) {
            $product->update(['price' => $current]);
        }

        PriceIntel::flush($product->id);

        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => "{$name} Review",
            'slug'         => str_replace(' ', '-', strtolower($name)) . '-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
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
            'category_id'   => $category->id,
            'name'          => 'Untracked Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00NEW',
            'price'         => 50,
        ]);
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Untracked Widget Review',
            'slug'         => 'untracked-widget-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
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
            ->assertSee('rel="canonical" href="' . route('deals') . '"', false);
    }

    public function test_deals_is_in_the_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('deals'), false);
    }
}
