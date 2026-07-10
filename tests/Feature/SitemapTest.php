<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private function publishPostsInCategory(Category $category, int $count): void
    {
        static $n = 0;
        $user = User::factory()->create();

        foreach (range(1, $count) as $i) {
            $n++;
            Post::create([
                'user_id' => $user->id,
                'title' => "Sitemap Post {$n}",
                'slug' => "sitemap-post-{$n}",
                'type' => 'article',
                'body' => 'Body.',
                'status' => 'published',
                'published_at' => now()->subDays($i),
            ])->categories()->attach($category->id);
        }
    }

    public function test_sitemap_contains_trust_pages_and_posts(): void
    {
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio']);
        $this->publishPostsInCategory($category, 1);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('about'), false)
            ->assertSee(route('how-we-review'), false)
            ->assertSee('sitemap-post-1', false);
    }

    public function test_sitemap_only_includes_categories_with_enough_posts(): void
    {
        $thin = Category::create(['name' => 'Tablets', 'slug' => 'tablets']);
        $full = Category::create(['name' => 'Audio', 'slug' => 'audio']);

        $this->publishPostsInCategory($thin, Category::SITEMAP_MIN_POSTS - 1);
        $this->publishPostsInCategory($full, Category::SITEMAP_MIN_POSTS);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('category', 'audio'), false)
            ->assertDontSee(route('category', 'tablets'), false);
    }
}
