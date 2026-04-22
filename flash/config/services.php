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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'vertex_ai' => [
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS') ?: storage_path('app/google/service-account.json'),
        'project_id' => env('VERTEX_AI_PROJECT_ID', ''),
        'region' => env('VERTEX_AI_REGION', 'us-central1'),
    ],

    'gumroad' => [
        // Gumroad product permalink/short id (e.g. "mghcgm" for klekstudio.gumroad.com/l/membership)
        'product_id' => env('GUMROAD_PRODUCT_ID', 'mghcgm'),
        // Public checkout URL the frontend redirects users to
        'product_url' => env('GUMROAD_PRODUCT_URL', 'https://klekstudio.gumroad.com/l/membership'),
        // Per-variant checkout URLs. Get these from Gumroad: open the product page,
        // pick the variant, then copy the URL from the address bar (it will contain
        // ?variant=XXXX or ?option=XXXX). Paste each one in the matching env var.
        'monthly_url'    => env('GUMROAD_MONTHLY_URL',    'https://klekstudio.gumroad.com/l/membership'),
        'six_months_url' => env('GUMROAD_SIX_MONTHS_URL', 'https://klekstudio.gumroad.com/l/membership'),
        // License verify endpoint
        'verify_url' => env('GUMROAD_VERIFY_URL', 'https://api.gumroad.com/v2/licenses/verify'),
        // Plan slugs created on the fly when a webhook arrives
        'monthly_plan_slug' => env('GUMROAD_MONTHLY_PLAN_SLUG', 'gumroad-monthly'),
        'six_months_plan_slug' => env('GUMROAD_SIX_MONTHS_PLAN_SLUG', 'gumroad-6-months'),
    ],

];
