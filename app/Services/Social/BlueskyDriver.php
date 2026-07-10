<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\SocialPost;
use App\Support\ResponsiveImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Posts to Bluesky over the AT Protocol (no developer portal, no review —
 * an app password is the only credential). Three XRPC calls:
 *
 *   1. com.atproto.server.createSession  — handle + app password → JWT
 *   2. com.atproto.repo.uploadBlob       — optional link-card thumbnail
 *   3. com.atproto.repo.createRecord     — the app.bsky.feed.post record
 *
 * Bluesky renders no automatic link preview: the client must supply the
 * app.bsky.embed.external card itself, and raw text URLs/#tags are only
 * clickable when byte-offset facets are attached. A failed thumbnail never
 * fails the post — the card just renders without an image.
 */
class BlueskyDriver implements SocialDriver
{
    private const TIMEOUT = 15;

    // uploadBlob rejects blobs near 1MB; stay comfortably under.
    private const MAX_THUMB_BYTES = 900_000;

    public function isConfigured(): bool
    {
        return (bool) config('services.social.platforms.bluesky.handle')
            && (bool) config('services.social.platforms.bluesky.app_password');
    }

    public function publish(SocialPost $socialPost): DriverResult
    {
        $post = $socialPost->post;
        $service = rtrim((string) config('services.social.platforms.bluesky.service', 'https://bsky.social'), '/');
        $handle = ltrim((string) config('services.social.platforms.bluesky.handle'), '@');

        $session = Http::acceptJson()->timeout(self::TIMEOUT)
            ->post("{$service}/xrpc/com.atproto.server.createSession", [
                'identifier' => $handle,
                'password' => (string) config('services.social.platforms.bluesky.app_password'),
            ]);

        if ($session->failed()) {
            throw new RuntimeException('Bluesky login failed: '.($session->json('message') ?? "HTTP {$session->status()}"));
        }

        $jwt = (string) $session->json('accessJwt');
        $did = (string) $session->json('did');

        $record = [
            '$type' => 'app.bsky.feed.post',
            'text' => $socialPost->body,
            'createdAt' => now()->toIso8601ZuluString(),
            'langs' => ['en'],
            'embed' => $this->externalEmbed($service, $jwt, $post),
        ];

        if ($facets = $this->facets($socialPost->body)) {
            $record['facets'] = $facets;
        }

        $response = Http::acceptJson()->timeout(self::TIMEOUT)->withToken($jwt)
            ->post("{$service}/xrpc/com.atproto.repo.createRecord", [
                'repo' => $did,
                'collection' => 'app.bsky.feed.post',
                'record' => $record,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Bluesky createRecord failed: '.($response->json('message') ?? "HTTP {$response->status()}"));
        }

        // at://did:plc:…/app.bsky.feed.post/{rkey} → public bsky.app URL
        $uri = (string) $response->json('uri');
        $rkey = ($slash = strrchr($uri, '/')) ? substr($slash, 1) : null;

        return DriverResult::posted($rkey ? "https://bsky.app/profile/{$handle}/post/{$rkey}" : null);
    }

    /**
     * The link-preview card. uri/title/description are required by the lexicon
     * (description may be empty); thumb is attached only when an image uploads.
     *
     * @return array<string, mixed>
     */
    private function externalEmbed(string $service, string $jwt, Post $post): array
    {
        $external = [
            'uri' => route('posts.show', $post->slug),
            'title' => $post->title,
            'description' => trim((string) $post->excerpt),
        ];

        if ($thumb = $this->uploadThumb($service, $jwt, $post)) {
            $external['thumb'] = $thumb;
        }

        return ['$type' => 'app.bsky.embed.external', 'external' => $external];
    }

    /**
     * Upload the post's hero/featured image as the card thumbnail, preferring
     * the mid-size WebP variants ImageVariants generates (well under the blob
     * cap). Returns the blob ref, or null on any miss/failure — never throws.
     *
     * @return array<string, mixed>|null
     */
    private function uploadThumb(string $service, string $jwt, Post $post): ?array
    {
        try {
            $rel = ResponsiveImage::relativePath($post->hero_image ?? $post->featured_image);
            if ($rel === null) {
                return null;
            }

            $disk = Storage::disk('public');
            $info = pathinfo($rel);
            $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

            foreach (["{$dir}{$info['filename']}-960.webp", "{$dir}{$info['filename']}-480.webp", $rel] as $path) {
                if (! $disk->exists($path) || $disk->size($path) > self::MAX_THUMB_BYTES) {
                    continue;
                }

                $mime = str_ends_with($path, '.webp')
                    ? 'image/webp'
                    : ($disk->mimeType($path) ?: 'image/jpeg');

                $upload = Http::timeout(self::TIMEOUT)->withToken($jwt)
                    ->withBody($disk->get($path), $mime)
                    ->post("{$service}/xrpc/com.atproto.repo.uploadBlob");

                return $upload->successful() ? $upload->json('blob') : null;
            }
        } catch (\Throwable) {
            // Card without an image beats no post at all.
        }

        return null;
    }

    /**
     * Byte-offset facets that make raw-text URLs and #hashtags clickable
     * (Bluesky attaches no rich text on its own). preg offsets are byte
     * offsets, which is exactly what the lexicon wants.
     *
     * @return array<int, array<string, mixed>>
     */
    private function facets(string $text): array
    {
        $facets = [];

        if (preg_match_all('#https?://[^\s]+#', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$url, $start]) {
                $facets[] = [
                    'index' => ['byteStart' => $start, 'byteEnd' => $start + strlen($url)],
                    'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => $url]],
                ];
            }
        }

        if (preg_match_all('/(?<![\w\/])#([A-Za-z][A-Za-z0-9_]*)/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $i => [$tag, $tagStart]) {
                $hashStart = $matches[0][$i][1];
                $facets[] = [
                    'index' => ['byteStart' => $hashStart, 'byteEnd' => $tagStart + strlen($tag)],
                    'features' => [['$type' => 'app.bsky.richtext.facet#tag', 'tag' => $tag]],
                ];
            }
        }

        return $facets;
    }
}
