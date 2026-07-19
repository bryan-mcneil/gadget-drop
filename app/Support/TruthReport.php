<?php

namespace App\Support;

use App\Models\Product;
use Carbon\CarbonInterface;

/**
 * Post-event sale analysis over stored price snapshots: for a given event
 * window, how many tracked "deals" were actually deals? Pure read — no
 * network calls, no writes; thresholds come only from config/truth.php.
 * We grade deals, never the retailer.
 *
 * analyze() returns the report body; truth:report wraps it in an envelope
 * (slug + generated_at) and writes storage/app/truth/{slug}.json. Schema —
 * key order is construction order and must stay stable so year-over-year
 * artifacts diff cleanly:
 *
 * {
 *   "slug": "prime-day-2026",              // envelope, added by the command
 *   "generated_at": "2026-08-01T09:00:00+00:00",
 *   "window": {"from": "2026-07-07", "to": "2026-07-08"},   // inclusive
 *   "baseline_days": 30,
 *   "config": {                            // thresholds the run used
 *     "min_baseline_days": 14,
 *     "thresholds": {"real_deal": 0.95, "worse": 1.05}
 *   },
 *   "totals": {
 *     "tracked": 61,                       // products with >= 1 snapshot
 *     "judged": 45,                        // tracked minus unobserved minus insufficient
 *     "unobserved": 3,                     // observation gate: baseline fine, but no snapshot
 *                                          // recorded inside the event window
 *     "insufficient": 13                   // honesty gate: too little pre-event history
 *   },
 *   "classes": {                           // judged products only; pct is share of judged
 *     "real_deal":  {"count": 12, "pct": 25.0},
 *     "repackaged": {"count": 30, "pct": 62.5},
 *     "worse":      {"count": 6,  "pct": 12.5}
 *   },
 *   "headline": {
 *     "real_deal_pct": 25.0,               // convenience copy of classes.real_deal.pct
 *     "biggest_real_deal": {"product": "…", "post_slug": "…"|null, "discount_pct": 31.2} | null,
 *     "biggest_markup":    {"product": "…", "post_slug": "…"|null, "markup_pct": 12.0} | null,
 *     "median_discount_pct": 1.8 | null    // median over judged products
 *   },
 *   "products": [                          // real_deal, repackaged, worse, unobserved,
 *     {                                    // insufficient; best discount first within each class
 *       "product_id": 7,
 *       "name": "…",
 *       "post_slug": "…" | null,           // earliest published review, for linking
 *       "tracked_since": "2026-05-01",     // first snapshot date
 *       "classification": "real_deal" | "repackaged" | "worse" | "unobserved" | "insufficient",
 *       "pre_min": 99.99 | null,           // nulls on insufficient rows; unobserved rows keep
 *       "pre_avg": 104.50 | null,          // their (valid) pre-event stats
 *       "event_min": 79.99 | null,         // null on unobserved rows: nothing was seen
 *       "discount_pct": 20.0 | null        // vs pre_min; positive = cheaper during the event
 *     }
 *   ]
 * }
 */
class TruthReport
{
    /** Artifact sort order: the story first, the honesty ledger last. */
    private const CLASS_ORDER = ['real_deal' => 0, 'repackaged' => 1, 'worse' => 2, 'unobserved' => 3, 'insufficient' => 4];

    /** The classes that carry an actual event verdict. */
    private const JUDGED = ['real_deal', 'repackaged', 'worse'];

    /**
     * Absolute path of a report's JSON artifact. Built via storage_path()
     * directly — the `local` disk roots at storage/app/private, which is
     * not where truth:report writes.
     */
    public static function path(string $slug): string
    {
        return storage_path('app/truth'.DIRECTORY_SEPARATOR."{$slug}.json");
    }

    /**
     * Publication is double-gated (two keys to turn): a report is live only
     * when config/truth.php lists its slug with published => true AND its
     * artifact file exists. Returns slug => config entry, in config order.
     */
    public static function published(): array
    {
        return array_filter(
            config('truth.publish', []),
            fn ($entry, $slug) => ($entry['published'] ?? false) && is_file(self::path($slug)),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Decode a report artifact, or null when it is missing or corrupt.
     */
    public static function load(string $slug): ?array
    {
        $path = self::path($slug);

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Analyze every product with any snapshot over an inclusive event window.
     */
    public static function analyze(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $baselineDays = (int) config('truth.baseline_days');
        $minBaselineDays = (int) config('truth.min_baseline_days');
        $thresholds = config('truth.thresholds');

        $rows = Product::query()
            ->whereHas('priceSnapshots')
            ->with([
                'priceSnapshots' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                'posts' => fn ($q) => $q->published()->orderBy('published_at'),
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Product $product) => self::classify($product, $from, $to, $baselineDays, $minBaselineDays, $thresholds))
            ->all();

        usort($rows, fn ($a, $b) => [self::CLASS_ORDER[$a['classification']], -($a['discount_pct'] ?? 0), $a['product_id']]
            <=> [self::CLASS_ORDER[$b['classification']], -($b['discount_pct'] ?? 0), $b['product_id']]);

        $counts = array_fill_keys(array_keys(self::CLASS_ORDER), 0);
        foreach ($rows as $row) {
            $counts[$row['classification']]++;
        }

        $tracked = count($rows);
        $judged = $tracked - $counts['unobserved'] - $counts['insufficient'];
        $pct = fn (int $n) => $judged > 0 ? round($n / $judged * 100, 1) : null;

        return [
            'window' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'baseline_days' => $baselineDays,
            'config' => [
                'min_baseline_days' => $minBaselineDays,
                'thresholds' => [
                    'real_deal' => $thresholds['real_deal'],
                    'worse' => $thresholds['worse'],
                ],
            ],
            'totals' => [
                'tracked' => $tracked,
                'judged' => $judged,
                'unobserved' => $counts['unobserved'],
                'insufficient' => $counts['insufficient'],
            ],
            'classes' => [
                'real_deal' => ['count' => $counts['real_deal'], 'pct' => $pct($counts['real_deal'])],
                'repackaged' => ['count' => $counts['repackaged'], 'pct' => $pct($counts['repackaged'])],
                'worse' => ['count' => $counts['worse'], 'pct' => $pct($counts['worse'])],
            ],
            'headline' => self::headline($rows, $pct($counts['real_deal'])),
            'products' => $rows,
        ];
    }

    /**
     * One product's report row. Insufficient pre-event history means every
     * stat stays null — reported and counted, never guessed. A valid baseline
     * alone is not enough: at least one snapshot must have been RECORDED
     * inside the event window, or the row is `unobserved` (2026 Prime Day
     * pilot lesson — carry-forward flatness must never masquerade as an
     * event verdict). Carry-forward still fills gaps between event-window
     * snapshots; it just can't be the only event source.
     */
    private static function classify(Product $product, CarbonInterface $from, CarbonInterface $to, int $baselineDays, int $minBaselineDays, array $thresholds): array
    {
        $snapshots = $product->priceSnapshots
            ->map(fn ($s) => ['date' => $s->created_at->copy()->startOfDay(), 'price' => (float) $s->price])
            ->values();

        $trackedSince = $snapshots->first()['date'];

        $row = [
            'product_id' => $product->id,
            'name' => $product->name,
            'post_slug' => $product->posts->first()?->slug,
            'tracked_since' => $trackedSince->toDateString(),
            'classification' => 'insufficient',
            'pre_min' => null,
            'pre_avg' => null,
            'event_min' => null,
            'discount_pct' => null,
        ];

        if ($trackedSince->gt($from->copy()->subDays($minBaselineDays))) {
            return $row;
        }

        $baseline = PriceIntel::dailySeries($snapshots, $from->copy()->subDays($baselineDays), $from->copy()->subDay());

        $prePrices = array_column($baseline, 'price');
        $preMin = min($prePrices);

        // A zero/negative baseline can't anchor a ratio — bad data stays unjudged.
        if ($preMin <= 0) {
            return $row;
        }

        $row['pre_min'] = round($preMin, 2);
        $row['pre_avg'] = round(array_sum($prePrices) / count($prePrices), 2);

        // Event-observation gate: the history was fine, but nobody actually
        // saw a price during the event — the pre-event stats stand, the
        // event columns stay empty.
        if (! $snapshots->contains(fn ($s) => $s['date']->gte($from) && $s['date']->lte($to))) {
            $row['classification'] = 'unobserved';

            return $row;
        }

        $event = PriceIntel::dailySeries($snapshots, $from, $to);
        $eventMin = min(array_column($event, 'price'));

        $row['event_min'] = round($eventMin, 2);
        $row['discount_pct'] = round(($preMin - $eventMin) / $preMin * 100, 1);
        $row['classification'] = match (true) {
            $eventMin <= $preMin * $thresholds['real_deal'] => 'real_deal',
            $eventMin > $preMin * $thresholds['worse'] => 'worse',
            default => 'repackaged',
        };

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  sorted report rows
     */
    private static function headline(array $rows, ?float $realDealPct): array
    {
        $judged = array_values(array_filter($rows, fn ($r) => in_array($r['classification'], self::JUDGED, true)));

        // Rows are sorted best-discount-first within each class, so the first
        // real_deal row is the biggest real deal and the last worse row is
        // the biggest markup.
        $realDeals = array_values(array_filter($judged, fn ($r) => $r['classification'] === 'real_deal'));
        $worse = array_values(array_filter($judged, fn ($r) => $r['classification'] === 'worse'));

        $biggestRealDeal = $realDeals === [] ? null : [
            'product' => $realDeals[0]['name'],
            'post_slug' => $realDeals[0]['post_slug'],
            'discount_pct' => $realDeals[0]['discount_pct'],
        ];

        $biggestMarkup = $worse === [] ? null : [
            'product' => end($worse)['name'],
            'post_slug' => end($worse)['post_slug'],
            'markup_pct' => round(-end($worse)['discount_pct'], 1),
        ];

        return [
            'real_deal_pct' => $realDealPct,
            'biggest_real_deal' => $biggestRealDeal,
            'biggest_markup' => $biggestMarkup,
            'median_discount_pct' => self::median(array_column($judged, 'discount_pct')),
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return round($n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2, 1);
    }
}
