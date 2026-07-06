<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'gadgetdrop_api_key' => env('GADGETDROP_API_KEY'),

    // Google AdSense — flip ADSENSE_ENABLED=true in .env to enable.
    // Ad slots only appear on /tools/* pages. Update tools_slot once AdSense
    // approves the site and you create an ad unit in the AdSense dashboard.
    'adsense' => [
        'enabled'     => env('ADSENSE_ENABLED', false),
        'client'      => 'ca-pub-3856395634564582',
        'tools_slot'  => env('ADSENSE_TOOLS_SLOT', '8271358369'),
    ],

    'amazon' => [
        'affiliate_tag'  => env('AMAZON_AFFILIATE_TAG'),
        'pa_access_key'  => env('AMAZON_PA_ACCESS_KEY'),
        'pa_secret_key'  => env('AMAZON_PA_SECRET_KEY'),
        'pa_partner_tag' => env('AMAZON_PA_PARTNER_TAG'),
        'pa_host'        => env('AMAZON_PA_HOST', 'webservices.amazon.com'),
        'pa_region'      => env('AMAZON_PA_REGION', 'us-east-1'),
    ],

    /*
    | Canopy API (canopyapi.co) — interim Amazon product data for the price
    | tracker until PA-API access unlocks (3 qualifying sales). Free-tier-only
    | by design: stay on the Hobby plan (100 req/mo, no card attached) and keep
    | both budget knobs below that ceiling so cost is structurally $0.
    | daily_limit=3 ≈ every product refreshed roughly weekly at ~25 products.
    */
    'canopy' => [
        'api_key'        => env('CANOPY_API_KEY'),
        'monthly_budget' => (int) env('CANOPY_MONTHLY_BUDGET', 90),
        'daily_limit'    => (int) env('CANOPY_DAILY_LIMIT', 3),
    ],

    /*
    | Search Intel engine credentials. Google uses a service-account JSON key
    | (google/auth mints the bearer token, scope: webmasters); Bing uses a
    | single per-user API key. Both services no-op gracefully when unset, so
    | local dev and CI never need credentials. Behavioural config lives in
    | config/search.php.
    */
    'google_search_console' => [
        // Exactly as it appears in GSC: sc-domain:gadgetdrop.tech OR https://gadgetdrop.tech/
        'property'         => env('GSC_PROPERTY'),
        'credentials_path' => env('GSC_CREDENTIALS_PATH', 'storage/app/keys/gsc-service-account.json'),
    ],

    'bing_webmaster' => [
        'api_key'  => env('BING_WEBMASTER_API_KEY'),
        // Must match the verified site in Bing (imported from GSC).
        'site_url' => env('BING_SITE_URL', env('SEARCH_SITE_URL', 'https://gadgetdrop.tech')),
    ],

    /*
    | Social pipeline. SOCIAL_ENABLED is the master kill switch (default off,
    | like AdSense). Each platform has its own enable flag plus a mode:
    |   manual — pipeline composes the post, you paste it via /admin/social
    |   log    — LogDriver writes to the app log (staging / tests)
    |   api    — the platform's real API driver (Bluesky Phase 3, FB Phase 4)
    | Credentials live here so `php artisan optimize` (prod config cache) picks
    | up .env changes the usual way.
    */
    'social' => [
        'enabled'      => env('SOCIAL_ENABLED', false),
        'max_attempts' => (int) env('SOCIAL_MAX_ATTEMPTS', 3),
        'platforms' => [
            'bluesky' => [
                'enabled'      => env('SOCIAL_BLUESKY_ENABLED', false),
                'mode'         => env('SOCIAL_BLUESKY_MODE', 'manual'),
                'handle'       => env('BLUESKY_HANDLE'),
                'app_password' => env('BLUESKY_APP_PASSWORD'),
                'service'      => env('BLUESKY_SERVICE', 'https://bsky.social'),
            ],
            'facebook' => [
                'enabled'    => env('SOCIAL_FACEBOOK_ENABLED', false),
                'mode'       => env('SOCIAL_FACEBOOK_MODE', 'manual'),
                'page_id'    => env('FACEBOOK_PAGE_ID'),
                'page_token' => env('FACEBOOK_PAGE_TOKEN'),
                // Always pin: unversioned Graph calls default to the OLDEST
                // live version, not the newest.
                'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v25.0'),
            ],
        ],
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
