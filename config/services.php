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
        'tools_slot'  => env('ADSENSE_TOOLS_SLOT', ''),
    ],

    'amazon' => [
        'affiliate_tag'  => env('AMAZON_AFFILIATE_TAG'),
        'pa_access_key'  => env('AMAZON_PA_ACCESS_KEY'),
        'pa_secret_key'  => env('AMAZON_PA_SECRET_KEY'),
        'pa_partner_tag' => env('AMAZON_PA_PARTNER_TAG'),
        'pa_host'        => env('AMAZON_PA_HOST', 'webservices.amazon.com'),
        'pa_region'      => env('AMAZON_PA_REGION', 'us-east-1'),
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
