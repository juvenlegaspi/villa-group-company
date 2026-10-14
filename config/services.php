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

    'nominatim' => [
        'endpoint' => env('NOMINATIM_ENDPOINT', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'VillaGroupVesselTracking/1.0 (https://villagroupofcompanies.com)'),
    ],

    'met_norway' => [
        'endpoint' => env('MET_NORWAY_ENDPOINT', 'https://api.met.no/weatherapi/locationforecast/2.0'),
        'user_agent' => env('MET_NORWAY_USER_AGENT', 'VillaGroupVesselTracking/1.0 (https://villagroupofcompanies.com)'),
        'cache_minutes' => (int) env('MET_NORWAY_CACHE_MINUTES', 10),
    ],

    'searoutes' => [
        'api_key' => env('SEAROUTES_API_KEY'),
        'endpoint' => env('SEAROUTES_ENDPOINT', 'https://api.searoutes.com/route/v2/sea'),
        'connect_timeout' => (int) env('SEAROUTES_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('SEAROUTES_TIMEOUT', 25),
    ],

    'voyage_routes' => [
        // Use "local" without an API subscription. Change to "searoutes" after access is activated.
        'provider' => env('VOYAGE_ROUTE_PROVIDER', 'local'),
        'local_fallback' => env('VOYAGE_ROUTE_LOCAL_FALLBACK', true),
    ],

    'aisstream' => [
        'enabled' => env('AISSTREAM_ENABLED', false),
        'api_key' => env('AISSTREAM_API_KEY'),
        'endpoint' => env('AISSTREAM_ENDPOINT', 'wss://stream.aisstream.io/v0/stream'),
        'bounding_boxes' => [
            [
                [(float) env('AISSTREAM_NORTH_LATITUDE', 21.5), (float) env('AISSTREAM_WEST_LONGITUDE', 116.0)],
                [(float) env('AISSTREAM_SOUTH_LATITUDE', 4.0), (float) env('AISSTREAM_EAST_LONGITUDE', 127.5)],
            ],
        ],
        'minimum_log_interval_seconds' => (int) env('AISSTREAM_MIN_LOG_INTERVAL_SECONDS', 120),
        'minimum_log_distance_meters' => (float) env('AISSTREAM_MIN_LOG_DISTANCE_METERS', 100),
        'reconnect_max_seconds' => (int) env('AISSTREAM_RECONNECT_MAX_SECONDS', 60),
    ],

];
