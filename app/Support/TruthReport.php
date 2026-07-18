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
 *     "judged": 48,                        // tracked minus insufficient
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
 *   "products": [                          // real_deal, repackaged, worse, insufficient;
 *     {                                    // best discount first within each class
 *       "product_id": 7,
 *       "name": "…",
 *       "post_slug": "…" | null,           // earliest published review, for linking
 *       "tracked_since": "2026-05-01",     // first snapshot date
 *       "classification": "real_deal" | "repackaged" | "worse" | "insufficient",
 *       "pre_min": 99.99 | null,           // nulls on insufficient rows
 *       "pre_avg": 104.50 | null,
 *       "event_min": 79.99 | null,
 *       "discount_pct": 20.0 | null        // vs pre_min; positive = cheaper during the event
 *     }
 *   ]
 * }
 */
class TruthReport
{
    /** Artifact sort order: the story first, the honesty ledger last. */
    private const CLASS_ORDER = ['real_deal' => 0, 'repackaged' => 1, 'worse' => 2, 'insufficient' => 3];

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
        $judged = $tracked - $counts['insufficient'];
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
     * stat stays null — reported and counted, never guessed.
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
        $event = PriceIntel::dailySeries($snapshots, $from, $to);

        $prePrices = array_column($baseline, 'price');
        $preMin = min($prePrices);
        $eventMin = min(array_column($event, 'price'));

        // A zero/negative baseline can't anchor a ratio — bad data stays unjudged.
        if ($preMin <= 0) {
            return $row;
        }

        $row['pre_min'] = round($preMin, 2);
        $row['pre_avg'] = round(array_sum($prePrices) / count($prePrices), 2);
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
        $judged = array_values(array_filter($rows, fn ($r) => $r['classification'] !== 'insufficient'));

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
