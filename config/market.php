<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Market-wide price import (docs/MARKET-IMPORT.md)
    |--------------------------------------------------------------------------
    | `market:import` streams an externally produced CSV into market_products
    | / market_price_snapshots and merges matching ASINs into the curated
    | price layer. All report thresholds live here so the importer stays
    | judgment-free.
    */

    // A known ASIN whose price fell at least this fraction below its previous
    // market snapshot is listed as a notable mover in the run report.
    'mover_drop_pct' => 0.05,

    // Caps on the detail arrays kept in the report artifact. Totals are
    // always full counts; only the listed detail rows are truncated.
    'movers_cap' => 25,
    'new_lows_cap' => 25,
    'rejects_cap' => 50,

    // A tracked ASIN not seen by any import in this many days counts as
    // stale in the report's coverage block.
    'stale_days' => 7,

    // The only currency the importer accepts; rows in any other currency are
    // rejected (mixed-currency history would poison every comparison).
    'currency' => 'USD',
];
