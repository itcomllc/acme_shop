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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'square' => [
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'access_token' => env('SQUARE_ACCESS_TOKEN', null),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'webhook_secret' => env('SQUARE_WEBHOOK_SECRET'),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        // 定期支払いプランID（Square Dashboard で事前作成）
        'plan_basic' => env('SQUARE_PLAN_BASIC'),
        'plan_professional' => env('SQUARE_PLAN_PROFESSIONAL'),
        'plan_enterprise' => env('SQUARE_PLAN_ENTERPRISE'),
    ],

    'gogetssl' => [
        'username' => env('GOGETSSL_USERNAME'),
        'password' => env('GOGETSSL_PASSWORD'),
        'base_url' => env('GOGETSSL_BASE_URL', 'https://my.gogetssl.com/api'),
        'partner_code' => env('GOGETSSL_PARTNER_CODE'),
        'timeout' => env('GOGETSSL_TIMEOUT', 30),
        'sandbox' => env('GOGETSSL_SANDBOX', false),
    ],

];
