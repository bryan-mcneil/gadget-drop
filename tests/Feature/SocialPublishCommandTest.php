<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialPublishCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social.enabled' => true,
            'services.social.platforms.bluesky.enabled' => true,
        ]);
    }

    private function publishedPost(array $overrides = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'user_id'      => User::factory()->create()->id,
            'title'        => "Command Post {$i}",
            'slug'         => "command-post-{$i}",
            'type'         => 'article',
            'excerpt'      => 'Short excerpt.',
            'body'         => 'Body text.',
            'status'       => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_noop_while_the_kill_switch_is_off(): void
    {
        config(['services.social.enabled' => false]);

        $this->artisan('social:publish')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_manual_mode_parks_rows_in_the_ready_queue(): void
    {
        $post = $this->publishedPost(); // default mode is manual

        $this->artisan('social:publish')
            ->expectsOutputToContain('manual posting')
            ->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_READY, SocialPost::where('post_id', $post->id)->first()->status);
    }

    public function test_log_mode_marks_rows_posted(): void
    {
        config(['services.social.platforms.bluesky.mode' => 'log']);
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_POSTED, $row->status);
        $this->assertNotNull($row->posted_at);
    }

    public function test_dry_run_changes_nothing(): void
    {
        config(['services.social.platforms.bluesky.mode' => 'log']);
        $post = $this->publishedPost();

        $this->artisan('social:publish --dry-run')
            ->expectsOutputToContain('would publish')
            ->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_PENDING, SocialPost::where('post_id', $post->id)->first()->status);
    }

    public function test_repeated_failures_downgrade_to_the_manual_queue(): void
    {
        // A configured API driver whose endpoint keeps erroring — the retry path.
        config([
            'services.social.platforms.bluesky.mode' => 'api',
            'services.social.platforms.bluesky.handle' => 'gadgetdrop.tech',
            'services.social.platforms.bluesky.app_password' => 'xxxx-xxxx-xxxx-xxxx',
        ]);
        \Illuminate\Support\Facades\Http::fake([
            'bsky.social/*' => \Illuminate\Support\Facades\Http::response(['message' => 'Upstream Failure'], 502),
        ]);
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();
        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_PENDING, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertNotNull($row->last_error);

        $this->artisan('social:publish')->assertSuccessful();
        $this->artisan('social:publish')->assertSuccessful();

        $row->refresh();
        $this->assertSame(SocialPost::STATUS_READY, $row->status);
        $this->assertSame(3, $row->attempts);
    }

    public function test_unpublished_posts_are_skipped(): void
    {
        $post = $this->publishedPost();
        $post->update(['status' => 'draft']);

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_SKIPPED, SocialPost::where('post_id', $post->id)->first()->status);
    }

    public function test_scheduled_ahead_posts_wait_until_live(): void
    {
        $post = $this->publishedPost(['published_at' => now()->addDay()]);

        $this->artisan('social:publish')
            ->expectsOutputToContain('waiting')
            ->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_PENDING, SocialPost::where('post_id', $post->id)->first()->status);
    }

    public function test_rows_stuck_pending_past_the_recency_window_are_skipped(): void
    {
        $post = $this->publishedPost();
        $post->forceFill(['published_at' => now()->subDays(3)])->saveQuietly();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_SKIPPED, SocialPost::where('post_id', $post->id)->first()->status);
    }
}
