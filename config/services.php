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

    'mercadopago' => [
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'public_key' => env('MERCADOPAGO_PUBLIC_KEY'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'success_url' => env('MERCADOPAGO_SUCCESS_URL', env('FRONTEND_URL', 'http://localhost:3000') . '/payment/success'),
        'failure_url' => env('MERCADOPAGO_FAILURE_URL', env('FRONTEND_URL', 'http://localhost:3000') . '/payment/failure'),
        'pending_url' => env('MERCADOPAGO_PENDING_URL', env('FRONTEND_URL', 'http://localhost:3000') . '/payment/pending'),
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL', env('APP_URL', 'http://localhost:8000') . '/api/v1/webhooks/mercadopago'),
    ],

    'andreani' => [
        'username' => env('ANDREANI_USERNAME'),
        'password' => env('ANDREANI_PASSWORD'),
        'contract_number' => env('ANDREANI_CONTRACT_NUMBER'),
        'origin_postal_code' => env('ANDREANI_ORIGIN_POSTAL_CODE'),
        'sender_document' => env('ANDREANI_SENDER_DOCUMENT'),
        'webhook_secret' => env('ANDREANI_WEBHOOK_SECRET'),
    ],

];
