<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The outbox trigger: publishing a post records one social_posts row per
 * enabled platform (via PostObserver), guarded by the kill switch, the 48h
 * recency window, and the unique (post_id, platform) idempotency key.
 */
class SocialOutboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social.enabled' => true,
            'services.social.platforms.bluesky.enabled' => true,
            'services.social.platforms.facebook.enabled' => true,
        ]);
    }

    private function makePost(array $overrides = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title'   => "Outbox Post {$i}",
            'slug'    => "outbox-post-{$i}",
            'type'    => 'article',
            'excerpt' => 'Short excerpt.',
            'body'    => 'Body text.',
            'status'  => 'draft',
        ], $overrides));
    }

    public function test_publishing_a_draft_queues_one_row_per_enabled_platform(): void
    {
        $post = $this->makePost();
        $this->assertSame(0, SocialPost::count());

        $post->update(['status' => 'published', 'published_at' => now()]);

        $this->assertEqualsCanonicalizing(
            ['bluesky', 'facebook'],
            SocialPost::where('post_id', $post->id)->pluck('platform')->all(),
        );
        $this->assertSame('pending', SocialPost::first()->status);
        $this->assertNotSame('', SocialPost::first()->body);
    }

    public function test_a_post_created_directly_as_published_is_also_queued(): void
    {
        $post = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(2, SocialPost::where('post_id', $post->id)->count());
    }

    public function test_republishing_never_duplicates_rows(): void
    {
        $post = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $post->update(['status' => 'draft']);
        $post->update(['status' => 'published']);

        $this->assertSame(2, SocialPost::where('post_id', $post->id)->count());
    }

    public function test_editing_an_old_published_post_never_announces_it(): void
    {
        $post = $this->makePost(['status' => 'published', 'published_at' => now()->subDays(30)]);

        $this->assertSame(0, SocialPost::count());

        $post->update(['status' => 'draft']);
        $post->update(['status' => 'published']); // e.g. a Sunday /drop-refresh cycle

        $this->assertSame(0, SocialPost::count());
    }

    public function test_the_kill_switch_stops_everything(): void
    {
        config(['services.social.enabled' => false]);

        $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(0, SocialPost::count());
    }

    public function test_disabled_platforms_are_not_queued(): void
    {
        config(['services.social.platforms.facebook.enabled' => false]);

        $post = $this->makePost(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(['bluesky'], SocialPost::where('post_id', $post->id)->pluck('platform')->all());
    }

    public function test_ordinary_edits_to_a_published_post_do_not_requeue(): void
    {
        $post = $this->makePost(['status' => 'published', 'published_at' => now()]);
        SocialPost::query()->delete(); // simulate an already-drained outbox

        $post->update(['excerpt' => 'Tweaked excerpt.']);

        $this->assertSame(0, SocialPost::count());
    }
}
