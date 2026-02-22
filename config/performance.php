<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public HTTP Cache (seconds)
    |--------------------------------------------------------------------------
    |
    | Used by the public.cache middleware for CDN/shared-cache responses.
    |
    */
    'public_http_cache' => [
        'default' => (int) env('HTTP_CACHE_TTL_DEFAULT', 120),
        'default_swr' => (int) env('HTTP_CACHE_SWR_DEFAULT', 300),

        'products' => (int) env('HTTP_CACHE_TTL_PRODUCTS', 120),
        'products_swr' => (int) env('HTTP_CACHE_SWR_PRODUCTS', 300),

        'product_detail' => (int) env('HTTP_CACHE_TTL_PRODUCT_DETAIL', 300),
        'product_detail_swr' => (int) env('HTTP_CACHE_SWR_PRODUCT_DETAIL', 600),

        'categories' => (int) env('HTTP_CACHE_TTL_CATEGORIES', 300),
        'categories_swr' => (int) env('HTTP_CACHE_SWR_CATEGORIES', 600),

        'settings' => (int) env('HTTP_CACHE_TTL_SETTINGS', 300),
        'settings_swr' => (int) env('HTTP_CACHE_SWR_SETTINGS', 600),

        'reviews' => (int) env('HTTP_CACHE_TTL_REVIEWS', 180),
        'reviews_swr' => (int) env('HTTP_CACHE_SWR_REVIEWS', 300),
    ],
];
