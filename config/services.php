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

    'webhook_secret' => env('WEBHOOK_SECRET'),

    'anthropic' => [
       'api_key' => env('ANTHROPIC_API_KEY'),
   ], 

   'n8n' => [
    'import_webhook_url' => env('N8N_IMPORT_WEBHOOK_URL'),
     'webhook_token'       => env('N8N_WEBHOOK_TOKEN'),
    ],

    'shopee' => [
        'mode' => env('SHOPEE_MODE', 'mock'),
        'partner_id' => env('SHOPEE_PARTNER_ID'),
        'partner_key' => env('SHOPEE_PARTNER_KEY'),
        'shop_id' => env('SHOPEE_SHOP_ID'),
        'redirect_url' => env('SHOPEE_REDIRECT_URL'),
        'api_base_url' => env(
            'SHOPEE_API_BASE_URL',
            'https://partner.shopeemobile.com'
        ),
    ],
];
