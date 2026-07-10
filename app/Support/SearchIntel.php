<?php

namespace App\Support;

use App\Models\SearchQueryDay;
use Illuminate\Support\Facades\Cache;

/**
 * Search-intelligence math — the PriceIntel twin for the opportunity miner and
 * the /admin/seo dashboard. Static entry points, db-cached 6h, defensive
 * try/catch so a broken cache degrades to a live compute (never a fatal).
 *
 * The centrepiece is an expected-CTR-by-position curve computed from OUR OWN
 * search_query_days (branding + SERP-feature skew is site-specific, so our
 * curve beats any industry table), with a config fallback for position buckets
 * we don't have enough sample for yet.
 */
class SearchIntel
{
    private const CURVE_KEY = 'searchintel.ctr_curve';

    /**
     * Expected-CTR by rounded position (1–20). Own-data median wins per bucket
     * once it clears the sample gate; config fallback fills the rest.
     *
     * @return array<int, float>
     */
    public static function ctrCurve(): array
    {
        try {
            return Cache::remember(self::CURVE_KEY, now()->addHours(6), fn () => self::computeCurve());
        } catch (\Throwable) {
            return self::computeCurve();
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CURVE_KEY);
        } catch (\Throwable) {
            // Cache not bound — nothing to flush.
        }
    }

    /**
     * Expected CTR at a SERP position. Pass an explicit $curve to keep it pure
     * (the miner resolves the curve once and threads it through every detector).
     *
     * @param  array<int, float>|null  $curve
     */
    public static function expectedCtr(float $position, ?array $curve = null): float
    {
        $curve ??= self::ctrCurve();
        $pos = (int) max(1, min(20, round($position)));

        return (float) ($curve[$pos] ?? config('search.ctr_curve.20', 0.005));
    }

    /**
     * Estimated clicks gained = impressions × the CTR headroom to a target
     * position. Clamped at 0 (a page already beating expectation isn't an
     * opportunity). Pure and explainable — comparable across detector kinds.
     */
    public static function score(int $impressions, float $expectedCtr, float $actualCtr): float
    {
        return round(max(0.0, $impressions * ($expectedCtr - $actualCtr)), 2);
    }

    /**
     * Build the own-data curve, falling back to config per position bucket.
     *
     * Buckets use an IMPRESSION-WEIGHTED CTR (Σclicks / Σimpressions), not an
     * unweighted median of per-row CTRs: most GSC rows are single-impression,
     * mostly zero-click long-tail, so a median would collapse to 0 and — worse —
     * override the sane fallback, zeroing a whole position's expected CTR (which
     * would disable ctr_fix and null out scores there). We also only override the
     * fallback with a strictly-positive signal, so a fluky all-zero bucket can't
     * regress below the config floor it was meant to improve on.
     *
     * @return array<int, float>
     */
    private static function computeCurve(): array
    {
        $curve = array_map('floatval', (array) config('search.ctr_curve', []));
        $minImpr = (int) config('search.min_ctr_samples', 200);

        $buckets = []; // pos => ['clicks' => int, 'impr' => int]

        SearchQueryDay::query()
            ->where('impressions', '>', 0)
            ->whereNotNull('position')
            ->select(['clicks', 'impressions', 'position'])
            ->chunk(2000, function ($rows) use (&$buckets) {
                foreach ($rows as $r) {
                    $pos = (int) round((float) $r->position);
                    if ($pos < 1 || $pos > 20) {
                        continue;
                    }
                    $buckets[$pos]['clicks'] = ($buckets[$pos]['clicks'] ?? 0) + (int) $r->clicks;
                    $buckets[$pos]['impr'] = ($buckets[$pos]['impr'] ?? 0) + (int) $r->impressions;
                }
            });

        foreach ($buckets as $pos => $b) {
            if (($b['impr'] ?? 0) >= $minImpr) {
                $ctr = round($b['clicks'] / $b['impr'], 4);
                if ($ctr > 0) {
                    $curve[$pos] = $ctr;
                }
            }
        }

        return $curve;
    }
}
