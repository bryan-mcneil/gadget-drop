<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * BlueskyDriver against a faked AT Protocol endpoint: session, record shape
 * (embed card, byte-offset facets), the public URL derived from the at:// uri,
 * failure retries, and the unconfigured-api fallback to the manual queue.
 */
class SocialBlueskyDriverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.social.enabled' => true,
            'services.social.platforms.bluesky.enabled' => true,
            'services.social.platforms.bluesky.mode' => 'api',
            'services.social.platforms.bluesky.handle' => 'gadgetdrop.tech',
            'services.social.platforms.bluesky.app_password' => 'xxxx-xxxx-xxxx-xxxx',
        ]);
    }

    private function publishedPost(array $overrides = []): Post
    {
        static $i = 0;
        $i++;

        return Post::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => "Bluesky Post {$i}",
            'slug' => "bluesky-post-{$i}",
            'type' => 'article',
            'excerpt' => 'Short excerpt.',
            'body' => 'Body text.',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function fakeHappyPath(): void
    {
        Http::fake([
            '*/xrpc/com.atproto.server.createSession' => Http::response([
                'accessJwt' => 'test-jwt',
                'did' => 'did:plc:abc123',
            ]),
            '*/xrpc/com.atproto.repo.createRecord' => Http::response([
                'uri' => 'at://did:plc:abc123/app.bsky.feed.post/3kabc',
                'cid' => 'bafycid',
            ]),
        ]);
    }

    public function test_a_pending_row_is_posted_with_the_public_bsky_url(): void
    {
        $this->fakeHappyPath();
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_POSTED, $row->status);
        $this->assertSame('https://bsky.app/profile/gadgetdrop.tech/post/3kabc', $row->external_url);
    }

    public function test_the_record_carries_the_link_card_and_clickable_facets(): void
    {
        $this->fakeHappyPath();
        $post = $this->publishedPost();
        $body = SocialPost::where('post_id', $post->id)->first()->body;
        $url = route('posts.show', $post->slug);

        $this->artisan('social:publish')->assertSuccessful();

        Http::assertSent(function (Request $request) use ($body, $url, $post) {
            if (! str_contains($request->url(), 'createRecord')) {
                return false;
            }

            $record = $request['record'];
            $linkFacets = collect($record['facets'] ?? [])
                ->filter(fn ($f) => ($f['features'][0]['$type'] ?? '') === 'app.bsky.richtext.facet#link');
            $tagFacets = collect($record['facets'] ?? [])
                ->filter(fn ($f) => ($f['features'][0]['$type'] ?? '') === 'app.bsky.richtext.facet#tag');

            return $record['text'] === $body
                && $record['embed']['$type'] === 'app.bsky.embed.external'
                && $record['embed']['external']['uri'] === $url
                && $record['embed']['external']['title'] === $post->title
                // The URL facet must sit at the URL's exact byte offsets.
                && $linkFacets->count() === 1
                && $linkFacets->first()['index']['byteStart'] === strpos($body, $url)
                && $linkFacets->first()['index']['byteEnd'] === strpos($body, $url) + strlen($url)
                && $tagFacets->count() === 2 // "#tech #gadgets" for articles
                && $tagFacets->first()['features'][0]['tag'] === 'tech';
        });
    }

    public function test_no_thumbnail_upload_is_attempted_without_an_image(): void
    {
        $this->fakeHappyPath();
        $this->publishedPost(); // no featured/hero image

        $this->artisan('social:publish')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'uploadBlob'));
    }

    public function test_a_failed_login_counts_as_a_retryable_attempt(): void
    {
        Http::fake([
            '*/xrpc/com.atproto.server.createSession' => Http::response(['message' => 'Invalid identifier or password'], 401),
        ]);
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $row = SocialPost::where('post_id', $post->id)->first();
        $this->assertSame(SocialPost::STATUS_PENDING, $row->status);
        $this->assertSame(1, $row->attempts);
        $this->assertStringContainsString('Invalid identifier', $row->last_error);
    }

    public function test_api_mode_without_credentials_falls_back_to_the_manual_queue(): void
    {
        config(['services.social.platforms.bluesky.app_password' => null]);
        Http::fake(); // nothing should be called
        $post = $this->publishedPost();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(SocialPost::STATUS_READY, SocialPost::where('post_id', $post->id)->first()->status);
        Http::assertNothingSent();
    }
}
