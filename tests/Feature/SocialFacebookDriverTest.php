<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialFacebookDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social.enabled' => true,
            'services.social.platforms.bluesky.enabled' => false,
            'services.social.platforms.facebook.enabled' => true,
            'services.social.platforms.facebook.mode' => 'api',
            'services.social.platforms.facebook.page_id' => '111222333',
            'services.social.platforms.facebook.page_token' => 'page-token-abc',
        ]);
    }

    private function publishedPost(): Post
    {
        static $i = 0;
        $i++;

        return Post::create([
            'user_id'      => User::factory()->create()->id,
            'title'        => "Facebook Post {$i}",
            'slug'         => "facebook-post-{$i}",
            'type'         => 'article',
            'excerpt'      => 'Short excerpt.',
            'body'         => 'Body text.',
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_a_pending_row_posts_to_the_pinned_graph_feed_endpoint(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '111222333_444'])]);
        $post = $this->publishedPost();
        $link = route('posts.show', $post->slug);

        $this->artisan('social:publish')->assertSuccessful();

        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_POSTED, $row->status);
        $this->assertSame('https://www.facebook.com/111222333_444', $row->external_url);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'graph.facebook.com/v25.0/111222333/feed')
            && $r['link'] === $link
            && $r['access_token'] === 'page-token-abc'
            // The bare URL line is stripped from the message — the link param
            // renders the card, so keeping it would duplicate the URL.
            && ! str_contains($r['message'], $link)
            && str_contains($r['message'], $post->title));
    }

    public function test_a_graph_error_is_a_retryable_attempt_with_the_message_recorded(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Error validating access token']], 400),
        ]);
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_PENDING, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertStringContainsString('Error validating access token', $row->last_error);
    }

    public function test_api_mode_without_a_page_token_falls_back_to_the_manual_queue(): void
    {
        config(['services.social.platforms.facebook.page_token' => null]);
        Http::fake();
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_READY, SocialPost::where('post_id', $post->id)->first()->status);
        Http::assertNothingSent();
    }
}
