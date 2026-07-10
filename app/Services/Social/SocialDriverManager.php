<?php

namespace App\Services\Social;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Resolves the driver for a platform from its configured mode
 * (config services.social.platforms.<platform>.mode):
 *
 *   manual — compose only, human pastes via /admin/social (the default)
 *   log    — write to the application log (staging / tests)
 *   api    — the platform's real API driver (Bluesky/Facebook, later phases)
 */
class SocialDriverManager
{
    public function driver(string $platform): SocialDriver
    {
        $mode = (string) config("services.social.platforms.{$platform}.mode", 'manual');

        return match ($mode) {
            'manual' => app(ManualDriver::class),
            'log' => app(LogDriver::class),
            'api' => $this->apiDriver($platform),
            default => throw new RuntimeException("Unknown social mode '{$mode}' for {$platform}."),
        };
    }

    private function apiDriver(string $platform): SocialDriver
    {
        $driver = match ($platform) {
            'bluesky' => app(BlueskyDriver::class),
            'facebook' => app(FacebookDriver::class),
            default => throw new RuntimeException("No API driver built for {$platform} yet — set its mode to 'manual'."),
        };

        if ($driver->isConfigured()) {
            return $driver;
        }

        // api mode without credentials degrades to the copy-paste queue
        // instead of burning retries — same "no-op without creds" contract
        // as the Search Intel services.
        Log::warning("{$platform} mode is \"api\" but its credentials are not set — routing to the manual queue.");

        return app(ManualDriver::class);
    }
}
