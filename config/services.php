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
    'esputnik' => [
        'login'    => env('ESPUTNIK_LOGIN'),
        'password' => env('ESPUTNIK_PASSWORD'),
        'from'     => env('ESPUTNIK_SMS_FROM', 'TRIPIROGI'),
        'sms_status_url' => env('ESPUTNIK_SMS_STATUS_URL'),
    ],


    'sms' => [
        'fake'      => env('SMS_FAKE', env('APP_ENV') === 'local'),
        'test_code' => env('SMS_TEST_CODE', '1234'),
    ],

    'liqpay' => [
        'public_key'  => env('LIQPAY_PUBLIC_KEY'),
        'private_key' => env('LIQPAY_PRIVATE_KEY'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
        'kitchen_lat' => env('GOOGLE_MAPS_KITCHEN_LAT'),
        'kitchen_lng' => env('GOOGLE_MAPS_KITCHEN_LNG'),
        'kitchen_address' => env('GOOGLE_MAPS_KITCHEN_ADDRESS'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'turnstile' => [
        'enabled' => (bool) env('TURNSTILE_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],

    'binotel' => [
        'webhook_public_url' => env('BINOTEL_WEBHOOK_PUBLIC_URL'),
        'crm_base_url' => env('BINOTEL_CRM_BASE_URL', env('APP_URL')),
        'webhook_secret' => env('BINOTEL_WEBHOOK_SECRET'),
        'admin_polling_enabled' => (bool) env('ADMIN_BINOTEL_POLLING_ENABLED', true),
        'ip_check_mode' => env('BINOTEL_IP_CHECK_MODE', 'strict'),
        'allowed_ips' => [
            '194.88.218.116', '194.88.218.114', '194.88.218.117', '194.88.218.118',
            '194.88.219.67', '194.88.219.78', '194.88.219.70', '194.88.219.71', '194.88.219.72',
            '194.88.219.79', '194.88.219.80', '194.88.219.81', '194.88.219.82', '194.88.219.83',
            '194.88.219.84', '194.88.219.85', '194.88.219.86', '194.88.219.87', '194.88.219.88',
            '194.88.219.89', '194.88.219.92', '194.88.218.119', '194.88.218.120',
            '185.100.66.145', '185.100.66.146', '185.100.66.147',
        ],
    ],

    'courier_comment' => [
        'admin_polling_enabled' => (bool) env('ADMIN_COURIER_COMMENT_POLLING_ENABLED', true),
    ],

    'pirogovaya_api' => [
        'enabled' => env('CALLCENTER_PIROGOVAYA_ENABLED', false),
        'name' => env('CALLCENTER_PIROGOVAYA_NAME', 'Pirogovaya'),
        'slug' => env('CALLCENTER_PIROGOVAYA_SLUG', 'pirogovaya'),
        'base_url' => env('CALLCENTER_PIROGOVAYA_BASE_URL'),
        'api_key' => env('CALLCENTER_PIROGOVAYA_API_KEY'),
        'orders_endpoint' => env('CALLCENTER_PIROGOVAYA_ORDERS_ENDPOINT', '/api/get-last-orders'),
    ],

    'pie_api' => [
        'enabled' => env('CALLCENTER_PIE_ENABLED', false),
        'name' => env('CALLCENTER_PIE_NAME', 'Pie'),
        'slug' => env('CALLCENTER_PIE_SLUG', 'pie'),
        'base_url' => env('CALLCENTER_PIE_BASE_URL'),
        'api_key' => env('CALLCENTER_PIE_API_KEY'),
        'orders_endpoint' => env('CALLCENTER_PIE_ORDERS_ENDPOINT', '/api/get-last-orders'),
    ],

    'timeshop' => [
        'menu_label' => env('TIMESHOP_MENU_LABEL', 'Timeshop'),
        'asset_url' => env('TIMESHOP_ASSET_URL', 'https://timeshop.com.ua'),
    ],

    'callcenter' => [
        'order_menu_source' => env('CALLCENTER_ORDER_MENU_SOURCE', 'main'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

];
