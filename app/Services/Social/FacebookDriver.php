<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posts to the GadgetDrop Facebook Page via the Graph API: one call to
 * POST /{page-id}/feed with the composed message, the review link (Facebook
 * builds the preview card from it), and the never-expiring Page access token
 * that social:fb-token mints. The Graph version is pinned — unversioned calls
 * default to the OLDEST live version, not the newest.
 *
 * While the Meta app is in Development Mode these posts are real but visible
 * only to app admins — that's the staging story before App Review.
 */
class FacebookDriver implements SocialDriver
{
    private const TIMEOUT = 15;

    public function isConfigured(): bool
    {
        return (bool) config('services.social.platforms.facebook.page_id')
            && (bool) config('services.social.platforms.facebook.page_token');
    }

    public function publish(SocialPost $socialPost): DriverResult
    {
        $config = config('services.social.platforms.facebook');
        $version = $config['graph_version'] ?? 'v25.0';
        $link = route('posts.show', $socialPost->post->slug);

        $response = Http::acceptJson()->timeout(self::TIMEOUT)
            ->post("https://graph.facebook.com/{$version}/{$config['page_id']}/feed", [
                'message'      => $this->messageWithoutBareLink($socialPost->body, $link),
                'link'         => $link,
                'access_token' => $config['page_token'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Facebook post failed: ' . ($response->json('error.message') ?? "HTTP {$response->status()}"));
        }

        // id is "{page_id}_{post_id}"; facebook.com/{id} resolves to the post.
        $id = (string) $response->json('id');

        return DriverResult::posted($id !== '' ? "https://www.facebook.com/{$id}" : null);
    }

    /**
     * The `link` param renders the preview card, so the bare URL line in the
     * composed copy (needed for Bluesky and manual pasting) would only
     * duplicate it here. Strip that one line, collapse leftover blank runs.
     */
    private function messageWithoutBareLink(string $body, string $link): string
    {
        $stripped = (string) preg_replace('/^' . preg_quote($link, '/') . '$/m', '', $body);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $stripped));
    }
}
