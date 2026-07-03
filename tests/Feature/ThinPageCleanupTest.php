<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the thin-page cleanup for the AdSense reapplication: tag pages are
 * noindexed, category pages need enough posts to be indexable, and the
 * methodology page exists and is linked.
 */
class ThinPageCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function publishPosts(int $count, ?Category $category = null, ?Tag $tag = null): void
    {
        static $n = 0;
        $user = User::factory()->create();

        foreach (range(1, $count) as $i) {
            $n++;
            $post = Post::create([
                'user_id'      => $user->id,
                'title'        => "Post {$n}",
                'slug'         => "post-{$n}",
                'type'         => 'article',
                'body'         => 'Body copy for a review.',
                'status'       => 'published',
                'published_at' => now()->subDays($i),
            ]);

            if ($category) {
                $post->categories()->attach($category->id);
            }
            if ($tag) {
                $post->tags()->attach($tag->id);
            }
        }
    }

    public function test_tag_pages_are_noindexed_but_render(): void
    {
        $tag = Tag::create(['name' => 'ANC', 'slug' => 'anc']);
        $this->publishPosts(2, tag: $tag);

        $this->get('/tag/anc')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_category_below_post_threshold_is_noindexed(): void
    {
        $category = Category::create(['name' => 'Cameras', 'slug' => 'cameras']);
        $this->publishPosts(Category::SITEMAP_MIN_POSTS - 1, category: $category);

        $this->get('/category/cameras')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);
    }

    public function test_category_at_post_threshold_is_indexable(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->publishPosts(Category::SITEMAP_MIN_POSTS, category: $category);

        $this->get('/category/audio')
            ->assertOk()
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_category_description_becomes_the_meta_description(): void
    {
        $category = Category::create([
            'name'        => 'Wearables',
            'slug'        => 'wearables',
            'description' => 'Smartwatches, fitness trackers, and smart rings — picked for battery life and real-world comfort, not spec-sheet bragging rights.',
        ]);
        $this->publishPosts(Category::SITEMAP_MIN_POSTS, category: $category);

        $this->get('/category/wearables')
            ->assertOk()
            ->assertSee('Smartwatches, fitness trackers, and smart rings', false);
    }

    public function test_how_we_review_page_renders_and_is_indexable(): void
    {
        $this->get('/how-we-review')
            ->assertOk()
            ->assertSee('How We Review Products', false)
            ->assertSee('research-based', false)
            ->assertDontSee('name="robots" content="noindex', false);
    }

    public function test_footer_links_to_how_we_review(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('How We Review', false);
    }

    public function test_seo_panel_noindex_flag_noindexes_the_post_and_drops_it_from_the_sitemap(): void
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Legacy Thin Post',
            'slug'         => 'legacy-thin-post',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);
        $post->seoMeta()->create(['noindex' => true]);

        $this->get('/posts/legacy-thin-post')
            ->assertOk()
            ->assertSee('name="robots" content="noindex', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('legacy-thin-post', false);
    }

    public function test_post_byline_links_to_how_we_review(): void
    {
        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => 'Byline Review',
            'slug'         => 'byline-review',
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->get("/posts/{$post->slug}")
            ->assertOk()
            ->assertSee('How we review', false);
    }
}
