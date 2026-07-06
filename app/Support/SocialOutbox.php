<?php

namespace App\Support;

use App\Models\Post;
use App\Models\SocialPost;

/**
 * Records the intent to announce a post socially: one social_posts row per
 * enabled platform, created when a post transitions to published. The hourly
 * social:publish command does the actual delivery.
 *
 * Guards, in order: the SOCIAL_ENABLED kill switch; a 48-hour recency window
 * on published_at so editing an old post never re-announces it; and a unique
 * (post_id, platform) row via firstOrCreate so an unpublish/republish cycle
 * can't announce twice.
 */
class SocialOutbox
{
    public const RECENCY_HOURS = 48;

    /**
     * @return int number of outbox rows created
     */
    public static function enqueue(Post $post): int
    {
        if (! config('services.social.enabled')) {
            return 0;
        }

        if ($post->published_at === null || $post->published_at->lt(now()->subHours(self::RECENCY_HOURS))) {
            return 0;
        }

        $composer = new SocialComposer();
        $created = 0;

        foreach (config('services.social.platforms', []) as $platform => $settings) {
            if (! ($settings['enabled'] ?? false)) {
                continue;
            }

            $row = SocialPost::firstOrCreate(
                ['post_id' => $post->id, 'platform' => $platform],
                ['status' => SocialPost::STATUS_PENDING, 'body' => $composer->compose($post, $platform)],
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
