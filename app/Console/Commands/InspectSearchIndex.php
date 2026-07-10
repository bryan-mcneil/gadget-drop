<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\SearchIndexCheck;
use App\Models\SearchSubmission;
use App\Services\GoogleSearchConsoleService;
use App\Services\IndexNowService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Verify indexation of recent / not-yet-indexed posts via the GSC URL
 * Inspection API. Scheduled daily at 09:00 UTC (quota is 2,000/day; we use a
 * handful). No-op without GSC credentials.
 *
 * Candidates: posts published in the last 14 days, plus any published post
 * whose most recent check wasn't "indexed" (PASS). Capped per run. A post still
 * unindexed beyond the escalation window gets re-pinged (IndexNow + sitemap) and
 * surfaced for the /morning checklist.
 */
class InspectSearchIndex extends Command
{
    protected $signature = 'search:inspect {--limit= : Max URLs to inspect this run}';

    protected $description = 'Inspect index status of recent / not-yet-indexed posts via GSC URL Inspection.';

    public function handle(GoogleSearchConsoleService $gsc, IndexNowService $indexNow): int
    {
        if (! $gsc->isConfigured()) {
            $this->info('Google Search Console not configured — skipping index inspection.');

            return self::SUCCESS;
        }

        $cap = (int) ($this->option('limit') ?: config('search.inspect_daily_cap', 50));
        $escalateDays = (int) config('search.inspect_escalate_days', 4);

        $candidates = $this->candidates($cap);

        if ($candidates->isEmpty()) {
            $this->info('No posts need inspection.');

            return self::SUCCESS;
        }

        $inspected = 0;
        $indexed = 0;
        $escalated = [];

        foreach ($candidates as $post) {
            $url = route('posts.show', $post->slug);
            $result = $gsc->inspectUrl($url);

            if ($result === null) {
                $this->warn("  {$post->slug}: inspection failed — left for next run.");

                continue;
            }

            $indexStatus = $result['inspectionResult']['indexStatusResult'] ?? [];
            $verdict = $indexStatus['verdict'] ?? null;

            SearchIndexCheck::create([
                'post_id' => $post->id,
                'url' => $url,
                'verdict' => $verdict,
                'coverage_state' => $indexStatus['coverageState'] ?? null,
                'last_crawl_at' => isset($indexStatus['lastCrawlTime']) ? Carbon::parse($indexStatus['lastCrawlTime']) : null,
                'raw' => $result,
                'checked_at' => now(),
            ]);

            $inspected++;
            $isIndexed = $verdict === 'PASS';
            $isIndexed && $indexed++;

            $ageDays = $post->published_at ? (int) $post->published_at->diffInDays(now()) : 0;

            if (! $isIndexed && $ageDays > $escalateDays) {
                $indexNow->submit([$url], 'retry');

                if (($status = $gsc->submitSitemap()) !== null) {
                    SearchSubmission::create([
                        'url' => (string) config('search.sitemap_url'),
                        'engine' => 'google_sitemap',
                        'trigger' => 'retry',
                        'response_code' => $status,
                        'submitted_at' => now(),
                    ]);
                }

                $escalated[] = $post->slug;
            }

            $this->line("  {$post->slug}: ".($verdict ?? 'unknown'));
        }

        $this->info("Inspected {$inspected}, indexed {$indexed}, escalated ".count($escalated).'.');

        if ($escalated !== []) {
            $this->warn("Still not indexed after {$escalateDays} days: ".implode(', ', $escalated));
        }

        return self::SUCCESS;
    }

    /**
     * Published posts that are either recent or not-yet-confirmed-indexed,
     * recent first, capped.
     *
     * @return Collection<int, Post>
     */
    private function candidates(int $cap)
    {
        $posts = Post::published()->orderByDesc('published_at')->get();

        if ($posts->isEmpty()) {
            return $posts;
        }

        $latest = SearchIndexCheck::whereIn('post_id', $posts->pluck('id'))
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('post_id');

        return $posts->filter(function (Post $post) use ($latest) {
            $recent = $post->published_at && $post->published_at->gte(now()->subDays(14));
            $check = $latest->get($post->id)?->first();

            return $recent || $check === null || $check->verdict !== 'PASS';
        })->take($cap)->values();
    }
}
