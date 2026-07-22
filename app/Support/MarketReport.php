<?php

namespace App\Support;

/**
 * Run report for market:import: build() turns the importer's stats into the
 * JSON artifact written to storage/app/market/import-{timestamp}.json.
 * Thresholds and caps come only from config/market.php. Schema — key order
 * is construction order and must stay stable so run-over-run artifacts diff
 * cleanly:
 *
 * {
 *   "run_at": "2026-07-21T14:30:00+00:00",
 *   "file": "market-2026-07-21.csv",       // basename of the imported CSV
 *   "config": {"mover_drop_pct": 0.05, "stale_days": 7},
 *   "totals":  {"rows": 4210, "imported": 4102, "rejected": 96, "duplicates": 12},
 *   "asins":   {"new": 350, "known": 3752, "total_tracked": 9800},
 *   "curated": {                           // the merge into the products layer
 *     "matched_asins": 41,                 // distinct imported ASINs with >= 1 curated match
 *     "products_updated": 43,              // price_changes + same_price_checks (asin is not
 *                                          // unique in products, so this can exceed matched)
 *     "price_changes": 9,
 *     "same_price_checks": 34,
 *     "stale_skipped": 0                   // scrape older than the product's last check
 *   },
 *   "movers":  {"total": 9,                // full count; listed is biggest drop first,
 *               "listed": [{"asin": "…", "title": "…", "previous": 129.99,
 *                           "current": 99.99, "drop_pct": 23.1}]},   // capped at movers_cap
 *   "new_lows":{"total": 6,                // full count; listed capped at new_lows_cap
 *               "listed": [{"asin": "…", "title": "…", "price": 99.99,
 *                           "previous_low": 109.00}]},
 *   "coverage":{"tracked_asins": 9800, "seen_this_run": 4102, "stale_asins": 1200,
 *               "categories": {"Headphones": 812}},   // whole market table, count desc
 *   "rejects": [{"line": 17, "asin": "B0XYZ" | null, "reason": "…"}],  // capped at rejects_cap;
 *                                          // totals.rejected always holds the full count
 *   "warnings": {"list_price": 0, "rating": 0, "review_count": 0, "url": 0, "scraped_at": 0},
 *                                          // optional fields nulled as unparseable
 *   "ignored_columns": ["sponsored_flag"]  // header columns outside the contract
 * }
 */
class MarketReport
{
    /**
     * Artifact path for a run starting now. Built via storage_path()
     * directly — the `local` disk roots at storage/app/private, which is
     * not where market:import writes.
     */
    public static function path(): string
    {
        return storage_path('app/market'.DIRECTORY_SEPARATOR.'import-'.now()->format('Y-m-d-His').'.json');
    }

    /**
     * @param  array<string, mixed>  $stats  as returned by MarketImportService::import()
     */
    public static function build(array $stats): array
    {
        $movers = $stats['movers'];
        usort($movers, fn ($a, $b) => $b['drop_pct'] <=> $a['drop_pct']);

        return [
            'run_at' => now()->toIso8601String(),
            'file' => $stats['file'],
            'config' => [
                'mover_drop_pct' => (float) config('market.mover_drop_pct'),
                'stale_days' => (int) config('market.stale_days'),
            ],
            'totals' => $stats['totals'],
            'asins' => $stats['asins'],
            'curated' => $stats['curated'],
            'movers' => [
                'total' => $stats['movers_total'],
                'listed' => array_slice($movers, 0, (int) config('market.movers_cap')),
            ],
            'new_lows' => [
                'total' => $stats['new_lows_total'],
                'listed' => $stats['new_lows'], // already capped at collection
            ],
            'coverage' => $stats['coverage'],
            'rejects' => $stats['rejects'],
            'warnings' => $stats['warnings'],
            'ignored_columns' => $stats['ignored_columns'],
        ];
    }
}
