<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Truth Report — sale-event deal analysis (docs/plans/05-truth-report.md)
    |--------------------------------------------------------------------------
    | `truth:report` grades every tracked product's event-window minimum
    | against its pre-event baseline minimum. We grade deals, never the
    | retailer. All thresholds live here so the analyzer stays judgment-free.
    */

    // Days of history immediately before the event window that form the
    // pre-event baseline (min/avg over a carry-forward daily series).
    'baseline_days' => 30,

    // Honesty gate: a product's first snapshot must be at least this many
    // days before the event starts, or it is reported as `insufficient`
    // (counted, never guessed). Mirrors PriceIntel::MIN_SPAN_DAYS — one
    // sitewide standard for "enough history to make a claim".
    'min_baseline_days' => 14,

    'thresholds' => [
        // real_deal: event min <= pre-event baseline min x this.
        'real_deal' => 0.95,

        // worse: event min > pre-event baseline min x this.
        // Between the two ratios the "deal" is repackaged — the sale price
        // was just... the price.
        'worse' => 1.05,
    ],

    /*
    |--------------------------------------------------------------------------
    | Publication map
    |--------------------------------------------------------------------------
    | Publication is config-explicit, never automatic: a report page goes
    | live only when its slug is listed here with published => true AND
    | storage/app/truth/{slug}.json exists (two keys to turn).
    */
    'publish' => [
        // 'prime-day-2026' => [
        //     'title' => 'Prime Day 2026: How Many Deals Were Actually Deals?',
        //     'published' => false,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event-week /deals promo
    |--------------------------------------------------------------------------
    | Slug of ONE report to promote in the /deals intro during event weeks,
    | or null (default: off). The slug must also pass the publication gate
    | above, or the line stays hidden. Flip back to null after the event —
    | the line is a moment-in-time pointer, not permanent furniture.
    */
    'promote_on_deals' => null,
];
