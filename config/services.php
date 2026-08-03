<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'imagemagick' => [
        'path' => env('IMAGEMAGICK_PATH', 'convert'),
        'timeout' => (int) env('IMAGEMAGICK_TIMEOUT', 45),
    ],

    'poppler' => [
        'path' => env('POPPLER_PATH', 'pdftoppm'),
        'timeout' => (int) env('POPPLER_TIMEOUT', 45),
    ],

    'markitdown' => [
        'path' => env('MARKITDOWN_PATH', base_path('.markitdown/venv/bin/markitdown')),
        'timeout' => (int) env('MARKITDOWN_TIMEOUT', 180),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
