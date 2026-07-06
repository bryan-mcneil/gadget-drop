<?php

namespace App\Observers;

use App\Models\Post;
use App\Models\SearchSubmission;
use App\Services\GoogleSearchConsoleService;
use App\Services\IndexNowService;
use App\Support\SocialOutbox;
use Illuminate\Support\Facades\Log;

/**
 * Announces a post to search engines the moment it goes live (or changes URL),
 * so indexation starts in seconds instead of whenever a crawler wanders by.
 * The same publish transition also queues the post into the social outbox
 * (SocialOutbox::enqueue — gated + recency-guarded there).
 *
 * Fires on transition to published, and on slug/title change of an
 * already-published post (a slug change also re-announces the OLD URL so
 * engines can drop it). posts:import creates drafts, so this only fires on real
 * publishes. Everything is inline (no queue on Hostinger), wrapped so a failed
 * ping never blocks the save, and gated by SEARCH_PING_ENABLED so
 * local/dev/test stay silent.
 *
 * created() and updated() are used (not saved()) so create-vs-edit is decided
 * by the event itself — wasRecentlyCreated persists on a reused instance and
 * would mis-read a later edit as a create.
 */
class PostObserver
{
    public function __construct(
        private IndexNowService $indexNow,
        private GoogleSearchConsoleService $gsc,
    ) {}

    public function created(Post $post): void
    {
        if ($post->status === 'published') {
            $this->ping($post, $this->publishUrls($post), 'publish');
            $this->announceSocial($post);
        }
    }

    public function updated(Post $post): void
    {
        // Draft → published (or any edit that flips status to published).
        if ($post->wasChanged('status') && $post->status === 'published') {
            $this->ping($post, $this->publishUrls($post), 'publish');
            $this->announceSocial($post);

            return;
        }

        if ($post->status !== 'published') {
            return;
        }

        // Slug/title change on an already-published post → re-announce (a view_count
        // bump changes neither, so page views never ping).
        if (! $post->wasChanged('slug') && ! $post->wasChanged('title')) {
            return;
        }

        $urls = $this->publishUrls($post);

        if ($post->wasChanged('slug') && ($old = $post->getOriginal('slug')) && $old !== $post->slug) {
            $urls[] = route('posts.show', $old);
        }

        $this->ping($post, $urls, 'update');
    }

    /**
     * Queue the post into the social outbox (SocialOutbox owns the kill switch
     * and 48h recency guard). Wrapped so a social hiccup never blocks a save.
     */
    private function announceSocial(Post $post): void
    {
        try {
            SocialOutbox::enqueue($post);
        } catch (\Throwable $e) {
            Log::warning('Social outbox enqueue failed', ['post' => $post->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function ping(Post $post, array $urls, string $trigger): void
    {
        if (! config('search.ping_enabled')) {
            return;
        }

        try {
            $this->indexNow->submit($urls, $trigger);

            $status = $this->gsc->submitSitemap();
            if ($status !== null) {
                SearchSubmission::create([
                    'url'           => (string) config('search.sitemap_url'),
                    'engine'        => 'google_sitemap',
                    'trigger'       => $trigger,
                    'response_code' => $status,
                    'submitted_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Post publish ping failed', ['post' => $post->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The URLs worth announcing for a published post: the post itself, the home
     * page (its listing changed), and its primary category page.
     *
     * @return array<int, string>
     */
    private function publishUrls(Post $post): array
    {
        $urls = [route('posts.show', $post->slug), route('home')];

        if ($category = $post->categories()->first()) {
            $urls[] = route('category', $category->slug);
        }

        return $urls;
    }
}
