<?php

namespace Tests\Feature\Admin;

use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialQueueTest extends TestCase
{
    use RefreshDatabase;

    private function readyRow(array $overrides = []): SocialPost
    {
        static $i = 0;
        $i++;

        $post = Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => "Social Post {$i}",
            'slug'         => "social-post-{$i}",
            'type'         => 'article',
            'body'         => 'Body.',
            'status'       => 'published',
            'published_at' => now(),
        ]);

        return SocialPost::create(array_merge([
            'post_id'  => $post->id,
            'platform' => 'bluesky',
            'status'   => SocialPost::STATUS_READY,
            'body'     => "Social Post {$i}\n\nhttps://gadgetdrop.tech/posts/social-post-{$i}",
        ], $overrides));
    }

    public function test_the_screen_requires_auth(): void
    {
        $this->get('/admin/social')->assertRedirect('/login');
    }

    public function test_index_shows_the_ready_queue_with_copy_paste_bodies_and_composer_links(): void
    {
        $row = $this->readyRow();

        $this->actingAs(User::factory()->create())
            ->get('/admin/social')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Social/Index')
                ->has('queue', 1)
                ->where('queue.0.id', $row->id)
                ->where('queue.0.body', $row->body)
                ->where('queue.0.compose_url', fn ($url) => str_starts_with($url, 'https://bsky.app/intent/compose?text='))
            );
    }

    public function test_x_rows_get_a_prefilled_intent_composer_link(): void
    {
        $row = $this->readyRow(['platform' => 'x']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/social')
            ->assertInertia(fn (Assert $page) => $page
                ->where('queue.0.compose_url', fn ($url) => str_starts_with($url, 'https://x.com/intent/post?text='))
            );
    }

    public function test_pending_and_history_sections_are_separated_from_the_queue(): void
    {
        $this->readyRow();
        $this->readyRow(['status' => SocialPost::STATUS_PENDING]);
        $this->readyRow(['status' => SocialPost::STATUS_POSTED, 'posted_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get('/admin/social')
            ->assertInertia(fn (Assert $page) => $page
                ->has('queue', 1)
                ->has('pending', 1)
                ->has('recent', 1)
            );
    }

    public function test_mark_posted_records_the_live_url(): void
    {
        $row = $this->readyRow();

        $this->actingAs(User::factory()->create())
            ->post("/admin/social/{$row->id}/posted", ['external_url' => 'https://bsky.app/profile/gadgetdrop.tech/post/abc'])
            ->assertRedirect();

        $row->refresh();
        $this->assertSame(SocialPost::STATUS_POSTED, $row->status);
        $this->assertNotNull($row->posted_at);
        $this->assertSame('https://bsky.app/profile/gadgetdrop.tech/post/abc', $row->external_url);
    }

    public function test_mark_posted_works_without_a_url(): void
    {
        $row = $this->readyRow();

        $this->actingAs(User::factory()->create())
            ->post("/admin/social/{$row->id}/posted")
            ->assertRedirect();

        $this->assertSame(SocialPost::STATUS_POSTED, $row->fresh()->status);
    }

    public function test_a_garbage_url_is_rejected(): void
    {
        $row = $this->readyRow();

        $this->actingAs(User::factory()->create())
            ->post("/admin/social/{$row->id}/posted", ['external_url' => 'not-a-url'])
            ->assertSessionHasErrors('external_url');

        $this->assertSame(SocialPost::STATUS_READY, $row->fresh()->status);
    }

    public function test_skip_parks_the_row_out_of_the_queue(): void
    {
        $row = $this->readyRow();

        $this->actingAs(User::factory()->create())
            ->post("/admin/social/{$row->id}/skip")
            ->assertRedirect();

        $this->assertSame(SocialPost::STATUS_SKIPPED, $row->fresh()->status);
    }
}
