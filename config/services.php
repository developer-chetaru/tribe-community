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

  'onesignal' => [
    'app_id' => env('ONESIGNAL_APP_ID'),
    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    'api_url' =>  'https://onesignal.com/api/v1/notifications',
    'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY', null),
],
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
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

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'public' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_version' => '2024-12-18',
        'currency' => 'gbp',
        'tax_rate_id' => env('STRIPE_TAX_RATE_ID'),
    ],

    'paypal' => [
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => 'GBP',
        'api_base_url' => env('PAYPAL_MODE') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com',
    ],

];
