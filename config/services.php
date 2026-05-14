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
        'property_id' => env('GOOGLE_ANALYTICS_PROPERTY_ID'),
        'credentials_json' => env('GOOGLE_ANALYTICS_CREDENTIALS_JSON'),
    ],

    'search_console' => [
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL'),
        'credentials_json' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS_JSON'),
    ],

];
