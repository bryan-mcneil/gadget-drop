<?php

namespace App\Services\Social;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Log;

/**
 * Staging driver: "publishes" to the application log. Lets the whole outbox →
 * command → driver path run end-to-end (locally and in tests) before any real
 * platform credentials exist. Select it with SOCIAL_<PLATFORM>_MODE=log.
 */
class LogDriver implements SocialDriver
{
    public function publish(SocialPost $socialPost): DriverResult
    {
        Log::info('[social:log-driver] would post', [
            'platform' => $socialPost->platform,
            'post_id' => $socialPost->post_id,
            'body' => $socialPost->body,
        ]);

        return DriverResult::posted();
    }
}
