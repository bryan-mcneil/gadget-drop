<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'A Published Drop',
            'slug' => 'original-slug',
            'type' => 'article',
            'excerpt' => 'Excerpt.',
            'body' => 'Body.',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    public function test_renaming_a_published_posts_slug_records_a_redirect_and_301s(): void
    {
        $post = $this->makePost();

        $post->update(['slug' => 'new-slug']);

        $this->assertDatabaseHas('post_slug_redirects', [
            'old_slug' => 'original-slug',
            'post_id' => $post->id,
        ]);

        $this->get('/posts/original-slug')
            ->assertStatus(301)
            ->assertRedirect(route('posts.show', 'new-slug'));

        $this->get('/posts/new-slug')->assertOk();
    }

    public function test_chained_renames_redirect_straight_to_the_current_slug(): void
    {
        $post = $this->makePost();
        $post->update(['slug' => 'second-slug']);
        $post->update(['slug' => 'third-slug']);

        $this->get('/posts/original-slug')
            ->assertStatus(301)
            ->assertRedirect(route('posts.show', 'third-slug'));

        $this->get('/posts/second-slug')
            ->assertStatus(301)
            ->assertRedirect(route('posts.show', 'third-slug'));
    }

    public function test_reclaiming_an_old_slug_drops_the_stale_redirect(): void
    {
        $post = $this->makePost();
        $post->update(['slug' => 'new-slug']);
        $post->update(['slug' => 'original-slug']);

        $this->assertDatabaseMissing('post_slug_redirects', ['old_slug' => 'original-slug']);

        $this->get('/posts/original-slug')->assertOk();

        $this->get('/posts/new-slug')
            ->assertStatus(301)
            ->assertRedirect(route('posts.show', 'original-slug'));
    }

    public function test_old_slug_404s_while_the_target_post_is_unpublished(): void
    {
        $post = $this->makePost();
        $post->update(['slug' => 'new-slug']);
        $post->update(['status' => 'draft']);

        $this->get('/posts/original-slug')->assertNotFound();
    }

    public function test_draft_slug_changes_record_nothing(): void
    {
        $post = $this->makePost(['status' => 'draft', 'slug' => 'draft-slug', 'published_at' => null]);

        $post->update(['slug' => 'draft-slug-2']);

        $this->assertDatabaseCount('post_slug_redirects', 0);
    }

    public function test_unknown_slugs_still_404(): void
    {
        $this->get('/posts/never-existed')->assertNotFound();
    }
}
