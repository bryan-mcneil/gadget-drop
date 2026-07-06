<?php

namespace App\Services\Social;

use App\Models\SocialPost;

/**
 * Outcome of a driver attempt: either the post is live (posted, optionally
 * with its public URL) or it is composed and waiting for a human to paste it
 * (ready — the manual queue).
 */
final class DriverResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $externalUrl = null,
    ) {}

    public static function posted(?string $externalUrl = null): self
    {
        return new self(SocialPost::STATUS_POSTED, $externalUrl);
    }

    public static function ready(): self
    {
        return new self(SocialPost::STATUS_READY);
    }
}
