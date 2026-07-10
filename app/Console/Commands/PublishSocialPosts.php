<?php

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Services\Social\SocialDriverManager;
use App\Support\SocialOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Drains the social outbox: every pending row goes through its platform's
 * driver. Runs hourly (minute :00, per the hPanel cron constraint) and is a
 * silent no-op while SOCIAL_ENABLED=false.
 *
 * Outcomes per row: posted (API drivers / LogDriver) · ready (ManualDriver —
 * waiting for a copy-paste) · skipped (post unpublished, or sat pending past
 * the 48h recency window) · left pending (post scheduled for the future, or a
 * failed attempt below the retry cap). After max_attempts failures a row is
 * downgraded to ready — a platform outage becomes a manual paste, never a
 * silently missed announcement.
 */
class PublishSocialPosts extends Command
{
    protected $signature = 'social:publish {--dry-run : Show what would be sent without changing anything}';

    protected $description = 'Drain the social outbox through each platform driver (no-op while SOCIAL_ENABLED=false).';

    public function handle(SocialDriverManager $drivers): int
    {
        if (! config('services.social.enabled')) {
            $this->info('Social pipeline disabled (SOCIAL_ENABLED=false) — skipping.');

            return self::SUCCESS;
        }

        $rows = SocialPost::pending()->with('post')->orderBy('created_at')->get();

        if ($rows->isEmpty()) {
            $this->info('Social outbox is empty — nothing to publish.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $maxAttempts = (int) config('services.social.max_attempts', 3);

        foreach ($rows as $row) {
            $label = "[{$row->platform}] \"".($row->post->title ?? "post #{$row->post_id}").'"';

            // The post may have been unpublished or deleted since it was queued.
            if ($row->post === null || $row->post->status !== 'published') {
                $this->markSkipped($row, 'Post no longer published.', $dryRun);
                $this->warn("{$label}: post no longer published — skipped.");

                continue;
            }

            // Scheduled-ahead publish date: announce only once the page is live.
            if ($row->post->published_at?->isFuture()) {
                $this->line("{$label}: goes live {$row->post->published_at->diffForHumans()} — waiting.");

                continue;
            }

            // A row that sat pending past the recency window is too old to announce.
            if ($row->post->published_at === null || $row->post->published_at->lt(now()->subHours(SocialOutbox::RECENCY_HOURS))) {
                $this->markSkipped($row, 'Stale: published more than '.SocialOutbox::RECENCY_HOURS.'h ago.', $dryRun);
                $this->warn("{$label}: older than ".SocialOutbox::RECENCY_HOURS.'h — skipped.');

                continue;
            }

            $mode = config("services.social.platforms.{$row->platform}.mode", 'manual');

            if ($dryRun) {
                $this->line("{$label}: would publish via '{$mode}' driver:");
                $this->line('  '.str_replace("\n", "\n  ", $row->body));

                continue;
            }

            try {
                $result = $drivers->driver($row->platform)->publish($row);

                if ($result->status === SocialPost::STATUS_POSTED) {
                    $row->update([
                        'status' => SocialPost::STATUS_POSTED,
                        'posted_at' => now(),
                        'external_url' => $result->externalUrl,
                        'last_error' => null,
                    ]);
                    $this->info("{$label}: posted.");
                } else {
                    $row->update(['status' => SocialPost::STATUS_READY]);
                    $this->info("{$label}: queued for manual posting (/admin/social).");
                }
            } catch (\Throwable $e) {
                $attempts = $row->attempts + 1;
                $error = Str::limit($e->getMessage(), 480);

                if ($attempts >= $maxAttempts) {
                    $row->update(['status' => SocialPost::STATUS_READY, 'attempts' => $attempts, 'last_error' => $error]);
                    $this->error("{$label}: failed {$attempts}x — downgraded to the manual queue. ({$error})");
                } else {
                    $row->update(['attempts' => $attempts, 'last_error' => $error]);
                    $this->warn("{$label}: attempt {$attempts} of {$maxAttempts} failed — will retry next run. ({$error})");
                }
            }
        }

        return self::SUCCESS;
    }

    private function markSkipped(SocialPost $row, string $reason, bool $dryRun): void
    {
        if (! $dryRun) {
            $row->update(['status' => SocialPost::STATUS_SKIPPED, 'last_error' => $reason]);
        }
    }
}
