<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the structural changes made to win Google AdSense approval:
 * tools de-indexed + out of nav, one affiliate CTA per post, a visible
 * verdict box, and clean 301s from the retired persona author URLs.
 */
class AdsensePrepTest extends TestCase
{
    use RefreshDatabase;

    public function test_tool_pages_are_noindexed(): void
    {
        $this->get('/tools')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/tools/json-validator')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_homepage_stays_indexable(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_tools_menu_removed_from_header(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee("toggle('tools')", false)
            ->assertDontSee("activeMenu === 'tools'", false);
    }

    public function test_legacy_persona_author_urls_redirect_to_real_author(): void
    {
        $target = '/author/' . config('site.author.slug');

        foreach (config('site.legacy_author_slugs') as $slug) {
            $this->get("/author/{$slug}")
                ->assertStatus(301)
                ->assertRedirect($target);
        }
    }

    public function test_review_renders_verdict_box(): void
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Widget Pro Review',
            'slug'         => 'widget-pro-review',
            'type'         => 'article',
            'body'         => 'A thorough review body that goes into the real-world experience.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
            'rating'       => 4.5,
            'pros'         => ['Great battery life', 'Comfortable fit'],
            'cons'         => ['No wireless charging'],
        ]);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('The Verdict', false)
            ->assertSee('Great battery life', false)
            ->assertSee('No wireless charging', false);
    }

    public function test_post_drops_the_duplicate_price_cta(): void
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Single CTA Review',
            'slug'         => 'single-cta-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $category = Category::firstOrCreate(['slug' => 'gadgets'], ['name' => 'Gadgets']);
        $product = Product::create([
            'category_id'   => $category->id,
            'name'          => 'Test Widget',
            'affiliate_url' => 'https://www.amazon.com/dp/B00TEST',
            'image_url'     => 'https://example.com/img.jpg',
            'price'         => 99,
            'description'   => 'A test gadget.',
        ]);
        $product->posts()->attach($post->id);

        // The single product card above the body is the only affiliate CTA now;
        // the repeated "Check Current Prices" block was removed.
        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertDontSee('Check Current Prices', false);
    }
}
