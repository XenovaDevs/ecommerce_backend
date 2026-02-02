<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for API behavior and settings.
    |
    */

    'version' => env('API_VERSION', 'v1'),

    'rate_limits' => [
        'default' => env('API_RATE_LIMIT_DEFAULT', 60),
        'auth' => env('API_RATE_LIMIT_AUTH', 5),
        'checkout' => env('API_RATE_LIMIT_CHECKOUT', 10),
    ],

    'pagination' => [
        'default_per_page' => 15,
        'max_per_page' => 100,
    ],

    'cache' => [
        'enabled' => env('API_CACHE_ENABLED', true),
        'ttl' => [
            'products' => 3600, // 1 hour
            'categories' => 7200, // 2 hours
            'settings' => 86400, // 24 hours
        ],
    ],

    'security' => [
        'require_https' => env('API_REQUIRE_HTTPS', true),
        'force_json' => true,
    ],
];
