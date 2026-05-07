<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'lemonsqueezy' => [
        'api_key' => env('LEMONSQUEEZY_API_KEY'),
        'store_id' => env('LEMONSQUEEZY_STORE_ID'),
        'variant_id' => env('LEMONSQUEEZY_VARIANT_ID'),
        'webhook_secret' => env('LEMONSQUEEZY_WEBHOOK_SECRET'),
        // Lemon Squeezy bills you in your store currency (BAM here). Our
        // catalog prices are in USD cents; multiply by this rate before
        // sending to LS so the customer pays the right BAM equivalent.
        // 1 USD ≈ 1.83 BAM (update occasionally).
        'usd_to_store_rate' => (float) env('LEMONSQUEEZY_USD_TO_STORE_RATE', 1.83),
        'success_url' => env('LEMONSQUEEZY_SUCCESS_URL'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect_uri' => env('GITHUB_REDIRECT_URI'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    ],
];
