<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Live Price Compare
    |--------------------------------------------------------------------------
    |
    | The on-demand "what does this cost elsewhere?" lookup on review pages.
    | Shares ANTHROPIC_API_KEY with the explain-verdict feature (see
    | config/services.php), but carries its OWN kill switch so it can be turned
    | off in production without pulling the key out from under that feature.
    |
    | Default off, same posture as ADSENSE_ENABLED: this costs real money per
    | click, so it never turns itself on by merely being deployed.
    |
    */

    'enabled' => env('PRICE_COMPARE_ENABLED', false),

    /*
     * Sonnet 5 rather than Haiku 4.5. Web search bills $10 per 1,000 searches
     * on top of tokens and that fee is model-independent, so the model choice
     * only moves the smaller token slice (roughly 15% of the per-click cost).
     * What it buys is judgment on `exact_model_match`, which is the only guard
     * in this design against pricing the wrong variant.
     */
    'model' => env('PRICE_COMPARE_MODEL', 'claude-sonnet-5'),

    /*
     * Circuit breaker, not a capacity plan. Uncached calls only, counted on the
     * database cache store under price-compare.usage.{Y-m-d}. At roughly $0.08
     * a call this caps a runaway day near $12; real usage sits far below it
     * because results are cached for 24h per product.
     */
    'daily_limit' => (int) env('PRICE_COMPARE_DAILY_LIMIT', 150),

    /*
     * LATENCY KNOB 1. Adaptive thinking is on by default on Sonnet 5 and this
     * call runs inside the reader's request, so thinking depth is wall-clock
     * the reader waits through. 'low' is the default: the task is lookup plus
     * one judgment call (exact_model_match), not reasoning. Raise to 'medium'
     * only if wrong-variant matches show up in the logs. Env-overridable so it
     * can be tuned on prod without a deploy (then `php artisan optimize`).
     */
    'effort' => env('PRICE_COMPARE_EFFORT', 'low'),

    /*
     * LATENCY KNOB 2. Web searches per request. Each search the model runs
     * one after another is a full round trip (model turn + search + model
     * turn), so this bounds the worst case. The system prompt also asks the
     * model to issue its searches together in one batch, which the API runs
     * in parallel; this cap is the hard stop behind that request.
     */
    'max_uses' => (int) env('PRICE_COMPARE_MAX_USES', 3),

    /*
     * Seconds. WE MUST FAIL BEFORE THE WEB SERVER DOES. The SDK defaults to a
     * 600s timeout with 2 retries; left alone it loses every race against PHP
     * max_execution_time and the LiteSpeed proxy, and the reader gets a dead
     * 504 instead of our graceful retry line. PriceCompareService pairs this
     * with maxRetries: 0, which is load-bearing: retries apply to timeouts, so
     * 2 of them would turn this into a 75s wall clock.
     */
    'timeout' => 25.0,

    /* Successful results (including "found nothing") are cached this long. */
    'cache_hours' => 24,

    /*
     * How recently our own tracked price must have been checked before we will
     * name a winner. Competitor prices come back live; ours is whatever we last
     * recorded, so past this window we still show the table and refuse the
     * claim. See App\Support\PriceComparison for why the bias runs toward
     * wrongly crowning Amazon.
     */
    'fresh_days' => 7,

    /* Below this self-reported confidence the whole widget is suppressed. */
    'confidence_floor' => 0.6,

    /*
     * THE COMPLIANCE BOUNDARY. Handed to the web search tool as
     * allowed_domains, and re-checked in PHP against every returned row.
     *
     * A whitelist rather than a blocklist, for three reasons:
     *   1. Amazon Associates 2(b) permits an Amazon price only when Amazon
     *      serves the link or it came from PA-API. A whitelist makes an Amazon
     *      price structurally impossible instead of dependent on a blocklist
     *      staying complete.
     *   2. It excludes price trackers and aggregators, which are bad 6(y)
     *      optics and unreliable as a source.
     *   3. It excludes marketplaces and resellers, which surface grey-market
     *      and refurb prices that read as fake lows.
     *
     * First-party US retailers only. NEVER add amazon.com. Keep this to
     * roughly 12-15 entries: an over-long domain filter comes back as the
     * request_too_large search error. allowed_domains and blocked_domains are
     * mutually exclusive, so never send both.
     */
    'retailers' => [
        'walmart.com',
        'bestbuy.com',
        'target.com',
        'costco.com',
        'samsclub.com',
        'newegg.com',
        'bhphotovideo.com',
        'adorama.com',
        'microcenter.com',
        'crutchfield.com',
        'staples.com',
        'homedepot.com',
        'lowes.com',
        'gamestop.com',
    ],

];
