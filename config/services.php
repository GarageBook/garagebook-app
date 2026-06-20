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

    'mailerlite' => [
        'base_url' => env('MAILERLITE_BASE_URL', 'https://connect.mailerlite.com/api'),
        'token' => env('MAILERLITE_API_TOKEN'),
        'group_id' => env('MAILERLITE_GROUP_ID'),
    ],

    'google_analytics' => [
        'auth_mode' => env('GOOGLE_ANALYTICS_AUTH_MODE', 'service_account'),
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        'client_id' => env('GOOGLE_ANALYTICS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ANALYTICS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ANALYTICS_REFRESH_TOKEN'),
        'credentials_json' => env('GOOGLE_ANALYTICS_CREDENTIALS_JSON'),
    ],

    'search_console' => [
        'auth_mode' => env('GOOGLE_SEARCH_CONSOLE_AUTH_MODE', 'service_account'),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
        'client_id' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN'),
        'credentials_json' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS_JSON'),
    ],

    'growth_report' => [
        'recipient' => env('GROWTH_REPORT_RECIPIENT', 'willemvanveelen@icloud.com'),
    ],

    'outreach' => [
        'daily_limit' => env('OUTREACH_DAILY_LIMIT', 100),
        'warning_threshold' => env('OUTREACH_WARNING_THRESHOLD', 95),
    ],

    'outreach_demo' => [
        'image_source_path' => env('OUTREACH_DEMO_IMAGE_SOURCE_PATH', '/temp/3'),
    ],

];
