<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\DailyDropImporterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidateCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_category_urls_redirect_to_their_target_hub(): void
    {
        foreach (config('site.category_map') as $old => $new) {
            $this->get("/category/{$old}")
                ->assertStatus(301)
                ->assertRedirect("/category/{$new}");
        }
    }

    public function test_consolidate_moves_posts_and_products_then_deletes_the_source(): void
    {
        $monitors  = Category::create(['name' => 'Monitors', 'slug' => 'monitors']);
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);

        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Monitor Review',
            'slug'         => 'monitor-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->categories()->attach($monitors->id);

        $product = Product::create([
            'category_id'   => $monitors->id,
            'name'          => 'UltraWide 34',
            'affiliate_url' => 'https://www.amazon.com/dp/B00MON',
            'price'         => 299,
        ]);

        $this->artisan('categories:consolidate')->assertSuccessful();

        $this->assertDatabaseMissing('categories', ['slug' => 'monitors']);
        $this->assertTrue($post->fresh()->categories->pluck('slug')->contains('computers'));
        $this->assertSame($computers->id, $product->fresh()->category_id);
    }

    public function test_consolidate_creates_a_missing_target_and_applies_renames(): void
    {
        Category::create(['name' => 'TVs', 'slug' => 'tvs']);

        $this->artisan('categories:consolidate')->assertSuccessful();

        $this->assertDatabaseHas('categories', ['slug' => 'audio', 'name' => 'Audio & Home Theater']);
        $this->assertDatabaseMissing('categories', ['slug' => 'tvs']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $monitors = Category::create(['name' => 'Monitors', 'slug' => 'monitors']);

        $this->artisan('categories:consolidate --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('categories', ['slug' => 'monitors']);
    }

    public function test_importer_maps_retired_category_slugs_to_the_target_hub(): void
    {
        $computers = Category::create(['name' => 'Computers', 'slug' => 'computers']);
        $user      = User::factory()->create();

        $post = app(DailyDropImporterService::class)->importOne([
            'title'         => 'Imported Monitor Post',
            'body'          => 'Body copy.',
            'category_name' => 'Monitors',
        ], $user->id);

        $this->assertTrue($post->categories->pluck('slug')->contains('computers'));
        $this->assertDatabaseMissing('categories', ['slug' => 'monitors']);
    }

    public function test_importer_maps_the_audio_display_name_to_the_audio_slug(): void
    {
        $user = User::factory()->create();

        $post = app(DailyDropImporterService::class)->importOne([
            'title'         => 'Imported Soundbar Post',
            'body'          => 'Body copy.',
            'category_name' => 'Audio & Home Theater',
        ], $user->id);

        $this->assertTrue($post->categories->pluck('slug')->contains('audio'));
        $this->assertDatabaseMissing('categories', ['slug' => 'audio-home-theater']);
    }

    public function test_importer_maps_the_computers_display_name_to_the_computers_slug(): void
    {
        $user = User::factory()->create();

        $post = app(DailyDropImporterService::class)->importOne([
            'title'         => 'Imported Power Bank Post',
            'body'          => 'Body copy.',
            'category_name' => 'Computers & Accessories',
        ], $user->id);

        $this->assertTrue($post->categories->pluck('slug')->contains('computers'));
        $this->assertDatabaseMissing('categories', ['slug' => 'computers-accessories']);
    }
}
