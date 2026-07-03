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
