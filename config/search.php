<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Intel — behavioural / tuning config
    |--------------------------------------------------------------------------
    | Credentials for the two engines live in config/services.php alongside the
    | other API keys (google_search_console, bing_webmaster). This file holds the
    | knobs that shape behaviour: what we announce, how long we retain data, and
    | the honesty gates the opportunity miner reads. Thresholds are intentionally
    | low for a young site and meant to be raised as data accumulates.
    */

    // Canonical site origin (no trailing slash) and the sitemap URL we re-nudge
    // Google with on publish. IndexNow "host" is derived from site_url.
    'site_url'    => rtrim(env('SEARCH_SITE_URL', 'https://gadgetdrop.tech'), '/'),
    'sitemap_url' => env('SEARCH_SITEMAP_URL', 'https://gadgetdrop.tech/sitemap.xml'),

    // IndexNow key. Served verbatim at /indexnow.txt; submissions pass it plus
    // keyLocation. Generate with: php -r "echo bin2hex(random_bytes(16));"
    'indexnow_key' => env('INDEXNOW_KEY'),

    // Master switch for outbound publish pings (IndexNow + Google sitemap
    // resubmit). Off everywhere except prod so local/dev/test never phone home.
    'ping_enabled' => (bool) env('SEARCH_PING_ENABLED', false),

    // GSC keeps 16 months; we mirror that and prune older rows in search:sync.
    'retention_months' => (int) env('SEARCH_RETENTION_MONTHS', 16),

    // Per-day URL Inspection cap (quota is 2,000/day; we need a handful).
    'inspect_daily_cap' => (int) env('SEARCH_INSPECT_CAP', 50),

    // A published post still not "indexed" after this many days escalates
    // (re-ping + /morning checklist flag).
    'inspect_escalate_days' => (int) env('SEARCH_INSPECT_ESCALATE_DAYS', 4),

    /*
    | Opportunity-miner thresholds (Phase 3). Every detector reads from here so
    | no magic numbers hide in code. `window_days` is the primary look-back.
    */
    'window_days'      => 28,
    'dismiss_days'     => 30,   // a dismissed opportunity stays quiet this long
    'min_ctr_samples'  => 200,  // min impressions per position bucket for own-CTR curve

    'thresholds' => [
        'striking_distance' => ['pos_min' => 4.0, 'pos_max' => 15.0, 'min_impressions' => 30],
        'ctr_fix'           => ['pos_max' => 12.0, 'ctr_ratio' => 0.5, 'min_impressions' => 100],
        'content_gap'       => ['min_impressions' => 20, 'weak_position' => 20.0],
        'decay'             => ['ratio' => 0.6, 'min_prior_clicks' => 20],
        'cannibalization'   => ['min_impressions' => 50, 'share' => 0.20, 'min_pages' => 2],
        'rising'            => ['multiplier' => 2.0, 'min_impressions' => 10],
    ],

    /*
    | Fallback expected-CTR curve by rounded SERP position. Used only for
    | position buckets where our own search_query_days lacks >= min_ctr_samples
    | impressions to compute a site-specific median. Own-data always wins.
    */
    'ctr_curve' => [
        1  => 0.280, 2  => 0.155, 3  => 0.100, 4  => 0.070, 5  => 0.050,
        6  => 0.038, 7  => 0.030, 8  => 0.024, 9  => 0.020, 10 => 0.017,
        11 => 0.014, 12 => 0.012, 13 => 0.010, 14 => 0.009, 15 => 0.008,
        16 => 0.007, 17 => 0.006, 18 => 0.006, 19 => 0.005, 20 => 0.005,
    ],
];
