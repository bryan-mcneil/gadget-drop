<?php

namespace App\Console\Commands;

use App\Models\BingQueryStat;
use App\Models\Post;
use App\Models\SearchPageDay;
use App\Models\SearchQueryDay;
use App\Models\SearchSiteDay;
use App\Services\BingWebmasterService;
use App\Services\GoogleSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Pull Google Search Console + Bing Webmaster data into the local grain tables,
 * idempotently. Scheduled daily at 07:00 UTC (after prices:refresh at 06:00).
 *
 *   php artisan search:sync                 # re-pull the last 5 days (default)
 *   php artisan search:sync --days=10
 *   php artisan search:sync --backfill=480  # one-time 16-month backfill
 *
 * Google is fetched with date-range requests (dimensions include `date`) so one
 * request covers the whole window per grain; long windows are split into 30-day
 * chunks to keep each response bounded. Everything upserts on its unique key, so
 * re-running restates fresh data without creating duplicates. Bing has no date
 * range, so its site trend is whatever the API returns and its per-query
 * aggregate is snapshotted weekly (Sundays).
 */
class SyncSearchData extends Command
{
    protected $signature = 'search:sync {--days=5 : Days back to re-pull} {--backfill= : One-time backfill window in days}';

    protected $description = 'Sync Google Search Console + Bing Webmaster data into the local search-intel tables.';

    /** @var array<string, int>|null slug => post id, built once per run */
    private ?array $slugMap = null;

    public function handle(GoogleSearchConsoleService $gsc, BingWebmasterService $bing): int
    {
        $days  = (int) ($this->option('backfill') ?: $this->option('days') ?: 5);
        $end   = Carbon::now()->startOfDay();
        $start = $end->copy()->subDays(max(0, $days - 1));

        $this->info("Search sync window: {$start->toDateString()} → {$end->toDateString()}.");

        if ($gsc->isConfigured()) {
            $this->syncGoogle($gsc, $start, $end);
        } else {
            $this->line('  Google Search Console not configured — skipping.');
        }

        if ($bing->isConfigured()) {
            $this->syncBing($bing, $end);
        } else {
            $this->line('  Bing Webmaster not configured — skipping.');
        }

        $this->prune();

        return self::SUCCESS;
    }

    private function syncGoogle(GoogleSearchConsoleService $gsc, Carbon $start, Carbon $end): void
    {
        $siteRows = 0;
        $pageRows = 0;
        $queryRows = 0;

        foreach ($this->dateChunks($start, $end) as [$chunkStart, $chunkEnd]) {
            $s = $chunkStart->toDateString();
            $e = $chunkEnd->toDateString();

            // Site/day totals per search type.
            foreach (['web', 'discover', 'googleNews'] as $type) {
                $resp = $gsc->searchAnalytics([
                    'startDate'  => $s,
                    'endDate'    => $e,
                    'dimensions' => ['date'],
                    'type'       => $type,
                ]);

                $siteRows += $this->upsertSiteDays($resp['rows'] ?? [], $type);
            }

            // Page/day (web).
            $resp = $gsc->searchAnalytics([
                'startDate'  => $s,
                'endDate'    => $e,
                'dimensions' => ['date', 'page'],
                'type'       => 'web',
            ]);
            $pageRows += $this->upsertPageDays($resp['rows'] ?? []);

            // Query x page / day (web).
            $resp = $gsc->searchAnalytics([
                'startDate'  => $s,
                'endDate'    => $e,
                'dimensions' => ['date', 'query', 'page'],
                'type'       => 'web',
            ]);
            $queryRows += $this->upsertQueryDays($resp['rows'] ?? []);
        }

        $this->info("  Google: {$siteRows} site-day, {$pageRows} page-day, {$queryRows} query-day rows.");
    }

    private function syncBing(BingWebmasterService $bing, Carbon $today): void
    {
        $rows = [];
        $now  = now();

        foreach ($bing->rankAndTrafficStats() as $stat) {
            $rows[] = [
                'source'      => 'bing',
                'date'        => $stat['date'],
                'search_type' => 'web',
                'clicks'      => $stat['clicks'],
                'impressions' => $stat['impressions'],
                'position'    => $stat['position'] !== null ? round($stat['position'], 2) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        $this->upsertChunked(SearchSiteDay::class, $rows, ['source', 'date', 'search_type'], ['clicks', 'impressions', 'position', 'updated_at']);
        $this->info('  Bing: ' . count($rows) . ' site-day rows.');

        // Bing's per-query aggregate has no date range — snapshot it weekly.
        if ($today->isSunday()) {
            $captured = $today->toDateString();
            $snap = [];

            foreach ($bing->queryStats() as $q) {
                $snap[] = [
                    'query'                   => $this->clip($q['query']),
                    'query_hash'              => sha1($q['query']),
                    'clicks'                  => $q['clicks'],
                    'impressions'             => $q['impressions'],
                    'avg_click_position'      => $q['avg_click_position'],
                    'avg_impression_position' => $q['avg_impression_position'],
                    'captured_on'             => $captured,
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ];
            }

            $this->upsertChunked(BingQueryStat::class, $snap, ['query_hash', 'captured_on'], ['query', 'clicks', 'impressions', 'avg_click_position', 'avg_impression_position', 'updated_at']);
            $this->info('  Bing: ' . count($snap) . ' query snapshot rows.');
        }
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertSiteDays(array $rows, string $searchType): int
    {
        $now  = now();
        $data = [];

        foreach ($rows as $r) {
            $date = $r['keys'][0] ?? null;
            if ($date === null) {
                continue;
            }

            $data[] = [
                'source'      => 'google',
                'date'        => $date,
                'search_type' => $searchType,
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'position'    => isset($r['position']) ? round((float) $r['position'], 2) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        $this->upsertChunked(SearchSiteDay::class, $data, ['source', 'date', 'search_type'], ['clicks', 'impressions', 'position', 'updated_at']);

        return count($data);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertPageDays(array $rows): int
    {
        $now  = now();
        $data = [];

        foreach ($rows as $r) {
            $date = $r['keys'][0] ?? null;
            $page = $r['keys'][1] ?? null;
            if ($date === null || $page === null) {
                continue;
            }

            $data[] = [
                'date'        => $date,
                'page_url'    => $this->clip($page),
                'url_hash'    => sha1($page),
                'post_id'     => $this->resolvePostId($page),
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'position'    => isset($r['position']) ? round((float) $r['position'], 2) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        $this->upsertChunked(SearchPageDay::class, $data, ['date', 'url_hash'], ['page_url', 'post_id', 'clicks', 'impressions', 'position', 'updated_at']);

        return count($data);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function upsertQueryDays(array $rows): int
    {
        $now  = now();
        $data = [];

        foreach ($rows as $r) {
            $date  = $r['keys'][0] ?? null;
            $query = $r['keys'][1] ?? null;
            $page  = $r['keys'][2] ?? null;
            if ($date === null || $query === null || $page === null) {
                continue;
            }

            $data[] = [
                'date'        => $date,
                'query'       => $this->clip($query),
                'query_hash'  => sha1($query),
                'page_url'    => $this->clip($page),
                'url_hash'    => sha1($page),
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'position'    => isset($r['position']) ? round((float) $r['position'], 2) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        $this->upsertChunked(SearchQueryDay::class, $data, ['date', 'query_hash', 'url_hash'], ['query', 'page_url', 'clicks', 'impressions', 'position', 'updated_at']);

        return count($data);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>  $update
     */
    private function upsertChunked(string $model, array $rows, array $uniqueBy, array $update): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $model::upsert($chunk, $uniqueBy, $update);
        }
    }

    private function resolvePostId(string $url): ?int
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        if (preg_match('#/posts/([^/]+)/?$#', $path, $m)) {
            $slug = urldecode($m[1]);

            return $this->slugMap()[$slug] ?? null;
        }

        return null;
    }

    /** @return array<string, int> */
    private function slugMap(): array
    {
        return $this->slugMap ??= Post::pluck('id', 'slug')->all();
    }

    /**
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function dateChunks(Carbon $start, Carbon $end, int $size = 30): array
    {
        $chunks = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $chunkEnd = $cursor->copy()->addDays($size - 1);
            if ($chunkEnd->gt($end)) {
                $chunkEnd = $end->copy();
            }
            $chunks[] = [$cursor->copy(), $chunkEnd];
            $cursor = $chunkEnd->copy()->addDay();
        }

        return $chunks;
    }

    private function clip(string $value): string
    {
        return mb_substr($value, 0, 500);
    }

    private function prune(): void
    {
        $cutoff = now()->subMonths((int) config('search.retention_months', 16))->toDateString();

        SearchSiteDay::whereDate('date', '<', $cutoff)->delete();
        SearchPageDay::whereDate('date', '<', $cutoff)->delete();
        SearchQueryDay::whereDate('date', '<', $cutoff)->delete();
        BingQueryStat::whereDate('captured_on', '<', $cutoff)->delete();
    }
}
