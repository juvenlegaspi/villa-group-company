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

    'semaphore' => [
        'enabled' => env('SEMAPHORE_ENABLED', false),
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
        'endpoint' => env('SEMAPHORE_ENDPOINT', 'https://api.semaphore.co/api/v4/messages'),
    ],

    'shipping_calendar' => [
        'catch_up_minutes' => (int) env('SHIPPING_CALENDAR_CATCH_UP_MINUTES', 1440),
        'max_delivery_attempts' => (int) env('SHIPPING_CALENDAR_MAX_DELIVERY_ATTEMPTS', 5),
        'max_attachments' => (int) env('SHIPPING_CALENDAR_MAX_ATTACHMENTS', 10),
        'max_attachment_bytes' => (int) env('SHIPPING_CALENDAR_MAX_ATTACHMENT_BYTES', 52428800),
    ],

];
