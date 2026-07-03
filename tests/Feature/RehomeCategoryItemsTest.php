<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RehomeCategoryItemsTest extends TestCase
{
    use RefreshDatabase;

    private function makeReviewedProduct(Category $category): array
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Smart Ring Review',
            'slug'         => 'smart-ring-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->categories()->attach($category->id);

        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'Smart Ring',
            'affiliate_url' => 'https://www.amazon.com/dp/B00RING',
            'price'         => 299,
        ]);
        $product->posts()->attach($post->id);

        return [$product, $post];
    }

    public function test_rehome_moves_the_product_and_swaps_the_category_on_its_posts(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        [$product, $post] = $this->makeReviewedProduct($computers);

        config(['site.category_rehome' => ['Smart Ring' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome')->assertSuccessful();

        $wearables = Category::where('slug', 'wearables')->firstOrFail();
        $this->assertSame('Wearables', $wearables->name);
        $this->assertSame($wearables->id, $product->fresh()->category_id);
        $this->assertSame(['wearables'], $post->fresh()->categories->pluck('slug')->all());
    }

    public function test_rehome_preserves_other_categories_on_the_post(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        $gaming = Category::create(['name' => 'Gaming', 'slug' => 'gaming']);
        [, $post] = $this->makeReviewedProduct($computers);
        $post->categories()->attach($gaming->id);

        config(['site.category_rehome' => ['Smart Ring' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome')->assertSuccessful();

        $slugs = $post->fresh()->categories->pluck('slug');
        $this->assertTrue($slugs->contains('wearables'));
        $this->assertTrue($slugs->contains('gaming'));
        $this->assertFalse($slugs->contains('computers'));
    }

    public function test_linked_posts_without_the_old_category_are_left_alone(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        [$product, $post] = $this->makeReviewedProduct($computers);

        // A tech tip loosely linked to the product but categorized elsewhere
        // (or not at all) must not inherit the product's new hub.
        $tip = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Unrelated Tip',
            'slug'         => 'unrelated-tip',
            'type'         => 'tech_tip',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $product->posts()->attach($tip->id);

        config(['site.category_rehome' => ['Smart Ring' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome')->assertSuccessful();

        $this->assertSame([], $tip->fresh()->categories->pluck('slug')->all());
        $this->assertSame(['wearables'], $post->fresh()->categories->pluck('slug')->all());
    }

    public function test_unknown_product_names_warn_without_failing(): void
    {
        config(['site.category_rehome' => ['Ghost Product' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome')->assertSuccessful();

        $this->assertDatabaseMissing('categories', ['slug' => 'wearables']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        [$product, $post] = $this->makeReviewedProduct($computers);

        config(['site.category_rehome' => ['Smart Ring' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome --dry-run')->assertSuccessful();

        $this->assertDatabaseMissing('categories', ['slug' => 'wearables']);
        $this->assertSame($computers->id, $product->fresh()->category_id);
        $this->assertSame(['computers'], $post->fresh()->categories->pluck('slug')->all());
    }

    public function test_rehome_is_idempotent(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        [, $post] = $this->makeReviewedProduct($computers);

        config(['site.category_rehome' => ['Smart Ring' => 'wearables'], 'site.category_rehome_posts' => []]);

        $this->artisan('categories:rehome')->assertSuccessful();
        $this->artisan('categories:rehome')->assertSuccessful();

        $this->assertSame(1, Category::where('slug', 'wearables')->count());
        $this->assertSame(['wearables'], $post->fresh()->categories->pluck('slug')->all());
    }

    public function test_rehome_attaches_categories_to_mapped_posts_without_detaching(): void
    {
        $gaming = Category::create(['name' => 'Gaming', 'slug' => 'gaming']);
        Category::create(['name' => 'Computers', 'slug' => 'computers']);

        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Hidden Shortcuts',
            'slug'         => 'hidden-shortcuts',
            'type'         => 'tech_tip',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->categories()->attach($gaming->id);

        config(['site.category_rehome' => [], 'site.category_rehome_posts' => ['hidden-shortcuts' => 'computers']]);

        $this->artisan('categories:rehome')->assertSuccessful();

        $slugs = $post->fresh()->categories->pluck('slug');
        $this->assertTrue($slugs->contains('computers'));
        $this->assertTrue($slugs->contains('gaming'));
    }
}
