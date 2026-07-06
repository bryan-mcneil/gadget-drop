<?php

namespace App\Services\Social;

use App\Models\SocialPost;

/**
 * The no-API path: the pipeline composes the full post, this driver simply
 * hands it to the copy-paste queue (/admin/social) by marking it ready.
 * Default mode for every platform until real credentials are configured, and
 * the failover target when an API driver exhausts its retries.
 */
class ManualDriver implements SocialDriver
{
    public function publish(SocialPost $socialPost): DriverResult
    {
        return DriverResult::ready();
    }
}
