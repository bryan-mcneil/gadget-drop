<?php

namespace App\Console\Commands;

use App\Models\BingQueryStat;
use App\Models\Post;
use App\Models\SearchOpportunity;
use App\Support\SearchIntel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Turn raw GSC/Bing rows into a ranked, deduplicated, honesty-gated list of
 * actions. Scheduled daily at 08:00 UTC (between sync 07:00 and inspect 09:00).
 *
 * Seven detectors run over a 28-day window; each emits candidates scored in
 * "estimated monthly clicks gained" so kinds are comparable. Candidates are
 * clustered by target page / head term (so the brief says "this topic, these 6
 * phrasings", not six rows), then upserted with a lifecycle: re-detection
 * refreshes score/evidence, a cleared condition auto-resolves to `done`, and a
 * dismissal is respected for 30 days. Every threshold lives in config/search.php.
 */
class MineSearchOpportunities extends Command
{
    protected $signature = 'search:mine';

    protected $description = 'Mine ranked, deduplicated search opportunities from synced GSC + Bing data.';

    /** @var array<string, int>|null slug => post id */
    private ?array $slugMap = null;

    /** @var array<int, float> */
    private array $curve = [];

    public function handle(): int
    {
        $windowDays  = (int) config('search.window_days', 28);
        $windowStart = now()->subDays($windowDays)->toDateString();
        $this->curve = SearchIntel::ctrCurve();

        $aggregates = $this->queryAggregates($windowStart);

        $candidates = [
            ...$this->strikingDistance($aggregates),
            ...$this->ctrFix($aggregates),
            ...$this->contentGap($aggregates),
            ...$this->cannibalization($aggregates),
            ...$this->decay($windowStart, $windowDays),
            ...$this->rising(),
            ...$this->bingGap($windowStart),
        ];

        $clusters = $this->cluster($candidates);

        $keptIds = [];
        foreach ($clusters as $cluster) {
            if ($id = $this->persist($cluster)) {
                $keptIds[] = $id;
            }
        }

        // Auto-resolve: an open opportunity whose condition no longer fires is done.
        SearchOpportunity::where('status', 'open')
            ->whereNotIn('id', $keptIds)
            ->update(['status' => 'done', 'last_seen_at' => now()]);

        $this->info('Mined ' . count($clusters) . ' opportunit' . (count($clusters) === 1 ? 'y' : 'ies') . '.');

        foreach (collect($clusters)->groupBy('kind') as $kind => $group) {
            $this->line("  {$kind}: " . $group->count());
        }

        return self::SUCCESS;
    }

    /*
    |--------------------------------------------------------------------------
    | Detectors — each returns candidate arrays (kind, cluster_key, query,
    | query_hash, post_id, page_url, impressions, clicks, position, target_pos,
    | score). Clustering + persistence happen downstream.
    |--------------------------------------------------------------------------
    */

    /** Our post ranking 4–15 with real demand → push it onto page 1. */
    private function strikingDistance($aggregates): array
    {
        $t   = config('search.thresholds.striking_distance');
        $out = [];

        foreach ($aggregates as $r) {
            if ($r->post_id === null || $r->impressions < $t['min_impressions'] || $r->avg_pos === null) {
                continue;
            }
            if ($r->avg_pos < $t['pos_min'] || $r->avg_pos > $t['pos_max']) {
                continue;
            }

            $ctr   = $r->clicks / $r->impressions;
            $score = SearchIntel::score($r->impressions, SearchIntel::expectedCtr(3, $this->curve), $ctr);

            $out[] = $this->candidate('striking_distance', 'post:' . $r->post_id, $r, 3, $score);
        }

        return $out;
    }

    /** Good position but CTR far below expected → rewrite title/meta (truthfully). */
    private function ctrFix($aggregates): array
    {
        $t   = config('search.thresholds.ctr_fix');
        $out = [];

        foreach ($aggregates as $r) {
            if ($r->post_id === null || $r->impressions < $t['min_impressions'] || $r->avg_pos === null) {
                continue;
            }
            if ($r->avg_pos > $t['pos_max']) {
                continue;
            }

            $ctr = $r->clicks / $r->impressions;
            $exp = SearchIntel::expectedCtr($r->avg_pos, $this->curve);

            if ($exp <= 0 || $ctr >= $exp * $t['ctr_ratio']) {
                continue;
            }

            $score = SearchIntel::score($r->impressions, $exp, $ctr);
            $out[] = $this->candidate('ctr_fix', 'post:' . $r->post_id, $r, (int) round($r->avg_pos), $score);
        }

        return $out;
    }

    /** Demand whose best result is home/category/off-topic (or ranks >20) → new post. */
    private function contentGap($aggregates): array
    {
        $t   = config('search.thresholds.content_gap');
        $out = [];

        foreach ($this->byQuery($aggregates) as $q) {
            if ($q['impressions'] < $t['min_impressions']) {
                continue;
            }

            $bestPostId  = $this->resolvePostId($q['best_page']);
            $rankedWeak  = $q['best_pos'] === null || $q['best_pos'] > $t['weak_position'];

            if ($bestPostId !== null && ! $rankedWeak) {
                continue; // a real post already ranks — not a gap
            }

            $score = SearchIntel::score($q['impressions'], SearchIntel::expectedCtr(5, $this->curve), 0.0);

            $out[] = [
                'kind'        => 'content_gap',
                'cluster_key' => 'gap:' . $this->headTerm($q['query']),
                'query'       => $q['query'],
                'query_hash'  => sha1($q['query']),
                'post_id'     => null,
                'page_url'    => $q['best_page'],
                'impressions' => $q['impressions'],
                'clicks'      => $q['clicks'],
                'position'    => $q['best_pos'],
                'target_pos'  => 5,
                'score'       => $score,
            ];
        }

        return $out;
    }

    /** Two+ of our posts splitting one query's demand → merge / differentiate. */
    private function cannibalization($aggregates): array
    {
        $t   = config('search.thresholds.cannibalization');
        $out = [];

        $grouped = collect($aggregates)->groupBy('query_hash');

        foreach ($grouped as $rows) {
            $total = $rows->sum('impressions');
            if ($total < $t['min_impressions']) {
                continue;
            }

            $posts = $rows->filter(fn ($r) => $r->post_id !== null)
                ->filter(fn ($r) => $r->impressions / $total >= $t['share']);

            if ($posts->count() < $t['min_pages']) {
                continue;
            }

            $head   = $rows->sortByDesc('impressions')->first();
            $aggCtr = $total > 0 ? $rows->sum('clicks') / $total : 0.0;

            $out[] = [
                'kind'        => 'cannibalization',
                'cluster_key' => 'cann:' . $head->query_hash,
                'query'       => $head->query,
                'query_hash'  => $head->query_hash,
                'post_id'     => null,
                'page_url'    => null,
                'impressions' => $total,
                'clicks'      => $rows->sum('clicks'),
                'position'    => $head->avg_pos,
                'target_pos'  => 3,
                // Estimated clicks recoverable by consolidating — same unit as every
                // other detector so cross-kind ranking stays meaningful.
                'score'       => SearchIntel::score((int) $total, SearchIntel::expectedCtr(3, $this->curve), $aggCtr),
                'competitors' => $posts->map(fn ($r) => [
                    'post_id'     => $r->post_id,
                    'page_url'    => $r->page_url,
                    'impressions' => $r->impressions,
                    'share'       => round($r->impressions / $total, 3),
                ])->values()->all(),
            ];
        }

        return $out;
    }

    /** A post's clicks collapsed vs its prior window → refresh it. */
    private function decay(string $windowStart, int $windowDays): array
    {
        $t          = config('search.thresholds.decay');
        $priorStart = now()->subDays($windowDays * 2)->toDateString();

        $current = DB::table('search_page_days')
            ->whereNotNull('post_id')->where('date', '>=', $windowStart)
            ->groupBy('post_id')->pluck(DB::raw('SUM(clicks)'), 'post_id');

        $prior = DB::table('search_page_days')
            ->whereNotNull('post_id')->where('date', '>=', $priorStart)->where('date', '<', $windowStart)
            ->groupBy('post_id')->pluck(DB::raw('SUM(clicks)'), 'post_id');

        $out = [];

        foreach ($prior as $postId => $priorClicks) {
            $priorClicks   = (int) $priorClicks;
            $currentClicks = (int) ($current[$postId] ?? 0);

            if ($priorClicks < $t['min_prior_clicks'] || $currentClicks >= $priorClicks * $t['ratio']) {
                continue;
            }

            $out[] = [
                'kind'        => 'decay',
                'cluster_key' => 'decay:' . $postId,
                'query'       => null,
                'query_hash'  => null,
                'post_id'     => (int) $postId,
                'page_url'    => null,
                'impressions' => 0,
                'clicks'      => $currentClicks,
                'position'    => null,
                'target_pos'  => null,
                'score'       => round((float) ($priorClicks - $currentClicks), 2),
                'decay'       => ['prior_clicks' => $priorClicks, 'current_clicks' => $currentClicks],
            ];
        }

        return $out;
    }

    /** A query surging week-over-week with no dedicated post → news/tip angle. */
    private function rising(): array
    {
        $t          = config('search.thresholds.rising');
        $recentFrom = now()->subDays(7)->toDateString();
        $priorFrom  = now()->subDays(14)->toDateString();

        $recent = DB::table('search_query_days')->where('date', '>=', $recentFrom)
            ->groupBy('query_hash')
            ->pluck(DB::raw('SUM(impressions)'), 'query_hash');

        $prior = DB::table('search_query_days')->where('date', '>=', $priorFrom)->where('date', '<', $recentFrom)
            ->groupBy('query_hash')
            ->pluck(DB::raw('SUM(impressions)'), 'query_hash');

        // A representative row per query for labelling + best-page lookup.
        $sample = DB::table('search_query_days')->where('date', '>=', $priorFrom)
            ->select('query_hash', DB::raw('MAX(query) as query'), DB::raw('MAX(page_url) as page_url'))
            ->groupBy('query_hash')->get()->keyBy('query_hash');

        $out = [];

        foreach ($recent as $hash => $recentImpr) {
            $recentImpr = (int) $recentImpr;
            $priorImpr  = (int) ($prior[$hash] ?? 0);

            if ($recentImpr < $t['min_impressions'] || $recentImpr < $priorImpr * $t['multiplier']) {
                continue;
            }

            $row = $sample->get($hash);
            if ($row === null || $this->resolvePostId($row->page_url) !== null) {
                continue; // already have a dedicated post
            }

            $out[] = [
                'kind'        => 'rising',
                'cluster_key' => 'rising:' . $this->headTerm($row->query),
                'query'       => $row->query,
                'query_hash'  => $hash,
                'post_id'     => null,
                'page_url'    => null,
                'impressions' => $recentImpr,
                'clicks'      => 0,
                'position'    => null,
                'target_pos'  => 5,
                'score'       => SearchIntel::score($recentImpr, SearchIntel::expectedCtr(5, $this->curve), 0.0),
                'rising'      => ['recent' => $recentImpr, 'prior' => $priorImpr],
            ];
        }

        return $out;
    }

    /** Strong Bing demand where Google is weak/absent → cross-engine arbitrage (report-only). */
    private function bingGap(string $windowStart): array
    {
        $latest = BingQueryStat::max('captured_on');
        if ($latest === null) {
            return [];
        }

        $googleHashes = DB::table('search_query_days')->where('date', '>=', $windowStart)
            ->distinct()->pluck('query_hash')->flip();

        $min = (int) config('search.thresholds.content_gap.min_impressions', 20);
        $out = [];

        foreach (BingQueryStat::where('captured_on', $latest)->get() as $stat) {
            if ($stat->impressions < $min || isset($googleHashes[$stat->query_hash])) {
                continue;
            }

            $out[] = [
                'kind'        => 'bing_gap',
                'cluster_key' => 'bing:' . $stat->query_hash,
                'query'       => $stat->query,
                'query_hash'  => $stat->query_hash,
                'post_id'     => null,
                'page_url'    => null,
                'impressions' => (int) $stat->impressions,
                'clicks'      => (int) $stat->clicks,
                'position'    => $stat->avg_impression_position !== null ? (float) $stat->avg_impression_position : null,
                'target_pos'  => 5,
                // Estimated clicks if we ranked ~5 on Google — clicks unit, comparable across kinds.
                'score'       => SearchIntel::score((int) $stat->impressions, SearchIntel::expectedCtr(5, $this->curve), 0.0),
            ];
        }

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | Clustering + persistence
    |--------------------------------------------------------------------------
    */

    /**
     * Group candidates by (kind, cluster_key). The highest-impression member is
     * the representative (its query keys the opportunity); the rest become
     * clustered phrasings in evidence. Scores sum across the cluster.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function cluster(array $candidates): array
    {
        $clusters = [];

        foreach ($candidates as $c) {
            $key = $c['kind'] . '|' . $c['cluster_key'];
            $clusters[$key][] = $c;
        }

        $out = [];

        foreach ($clusters as $members) {
            usort($members, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
            $head = $members[0];

            $evidence = [
                'impressions'     => array_sum(array_column($members, 'impressions')),
                'clicks'          => array_sum(array_column($members, 'clicks')),
                'position'        => $head['position'],
                'target_position' => $head['target_pos'],
                'phrasings'       => array_map(fn ($m) => array_filter([
                    'query'       => $m['query'],
                    'impressions' => $m['impressions'],
                    'clicks'      => $m['clicks'],
                    'position'    => $m['position'] !== null ? round((float) $m['position'], 1) : null,
                ], fn ($v) => $v !== null), $members),
            ];

            foreach (['competitors', 'decay', 'rising'] as $extra) {
                if (isset($head[$extra])) {
                    $evidence[$extra] = $head[$extra];
                }
            }

            $out[] = [
                'kind'        => $head['kind'],
                'cluster_key' => $head['cluster_key'],
                'query'       => $head['query'],
                'query_hash'  => $head['query_hash'],
                'post_id'     => $head['post_id'],
                'page_url'    => $head['page_url'],
                'score'       => round(array_sum(array_column($members, 'score')), 2),
                'evidence'    => $evidence,
            ];
        }

        return $out;
    }

    /**
     * Upsert one cluster with lifecycle handling. Identity is the stable
     * (kind, cluster_key) — NOT the representative phrasing's query_hash, which
     * flips between runs when impression counts change. query_hash/post_id/query
     * are descriptive and refreshed to the current head each run. Returns the row
     * id (or null when a still-active dismissal suppresses it).
     *
     * @param  array<string, mixed>  $c
     */
    private function persist(array $c): ?int
    {
        // Clip to the column width so lookup and write always use the same key.
        $key = mb_substr((string) $c['cluster_key'], 0, 100);

        $existing = SearchOpportunity::where('kind', $c['kind'])
            ->where('cluster_key', $key)
            ->first();

        $payload = [
            'query'        => $c['query'],
            'query_hash'   => $c['query_hash'],
            'post_id'      => $c['post_id'],
            'page_url'     => $c['page_url'],
            'score'        => $c['score'],
            'evidence'     => $c['evidence'],
            'last_seen_at' => now(),
        ];

        if ($existing) {
            // Respect a dismissal for the configured window (no zombie resurfacing).
            if ($existing->status === 'dismissed'
                && $existing->last_seen_at
                && $existing->last_seen_at->gt(now()->subDays((int) config('search.dismiss_days', 30)))) {
                return null;
            }

            // A cleared-then-returned problem reopens; planned stays planned.
            $payload['status'] = in_array($existing->status, ['done', 'dismissed'], true) ? 'open' : $existing->status;
            $existing->update($payload);

            return $existing->id;
        }

        return SearchOpportunity::create($payload + [
            'kind'          => $c['kind'],
            'cluster_key'   => $key,
            'status'        => 'open',
            'first_seen_at' => now(),
        ])->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Query helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Per (query_hash, url_hash) aggregate over the window, with post_id and
     * impression-weighted average position resolved. Returned as stdClass rows.
     */
    private function queryAggregates(string $windowStart)
    {
        return DB::table('search_query_days')
            ->where('date', '>=', $windowStart)
            ->groupBy('query_hash', 'url_hash')
            ->get([
                'query_hash',
                'url_hash',
                DB::raw('MAX(query) as query'),
                DB::raw('MAX(page_url) as page_url'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(position * impressions) as pos_weight'),
            ])
            ->map(function ($r) {
                $r->clicks      = (int) $r->clicks;
                $r->impressions = (int) $r->impressions;
                $r->avg_pos     = $r->impressions > 0 ? (float) $r->pos_weight / $r->impressions : null;
                $r->post_id     = $this->resolvePostId($r->page_url);

                return $r;
            });
    }

    /**
     * Collapse per-(query,page) aggregates to per-query totals with the best
     * (most-impressed) page and its position.
     *
     * @return array<int, array{query: string, query_hash: string, impressions: int, clicks: int, best_page: string, best_pos: ?float}>
     */
    private function byQuery($aggregates): array
    {
        $out = [];

        foreach (collect($aggregates)->groupBy('query_hash') as $rows) {
            $best = $rows->sortByDesc('impressions')->first();

            $out[] = [
                'query'       => $best->query,
                'query_hash'  => $best->query_hash,
                'impressions' => (int) $rows->sum('impressions'),
                'clicks'      => (int) $rows->sum('clicks'),
                'best_page'   => $best->page_url,
                // The rank of the page we resolve the post from — NOT the min across
                // all pages, so a well-ranking home/category page can't mask the fact
                // that our own post ranks poorly (a real content gap).
                'best_pos'    => $best->avg_pos,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection|object  $r
     * @return array<string, mixed>
     */
    private function candidate(string $kind, string $clusterKey, $r, ?int $targetPos, float $score): array
    {
        return [
            'kind'        => $kind,
            'cluster_key' => $clusterKey,
            'query'       => $r->query,
            'query_hash'  => $r->query_hash,
            'post_id'     => $r->post_id,
            'page_url'    => $r->page_url,
            'impressions' => $r->impressions,
            'clicks'      => $r->clicks,
            'position'    => $r->avg_pos,
            'target_pos'  => $targetPos,
            'score'       => $score,
        ];
    }

    private function headTerm(string $query): string
    {
        $words = preg_split('/\s+/', strtolower(trim($query))) ?: [];
        $words = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 2));

        return implode(' ', array_slice($words, 0, 2)) ?: strtolower(trim($query));
    }

    private function resolvePostId(?string $url): ?int
    {
        if ($url === null) {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');

        if (preg_match('#/posts/([^/]+)/?$#', $path, $m)) {
            return $this->slugMap()[urldecode($m[1])] ?? null;
        }

        return null;
    }

    /** @return array<string, int> */
    private function slugMap(): array
    {
        return $this->slugMap ??= Post::pluck('id', 'slug')->all();
    }
}
