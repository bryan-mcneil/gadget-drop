<?php

namespace App\Services\Social;

use App\Models\SocialPost;

/**
 * One driver per way of getting a composed post onto a platform. API drivers
 * (Bluesky, Facebook) transmit and return posted(); ManualDriver parks the row
 * in the copy-paste queue and returns ready(). Failures are thrown — the
 * social:publish command owns retry counting and the manual-queue failover.
 */
interface SocialDriver
{
    /**
     * @throws \RuntimeException when the attempt fails and should be retried
     */
    public function publish(SocialPost $socialPost): DriverResult;
}
