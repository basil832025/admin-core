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

    'nova_post' => [
        'key' => env('NOVA_POST_KEY'),
        'base_url' => env('NOVA_POST_BASE_URL', 'https://api.novaposhta.ua/v2.0/json/'),
        'timeout' => (int) env('NOVA_POST_TIMEOUT', 8),
        'sender_ref' => env('NOVA_POST_SENDER_REF'),
        'sender_contact_ref' => env('NOVA_POST_SENDER_CONTACT_REF'),
        'sender_city_ref' => env('NOVA_POST_SENDER_CITY_REF', '8d5a980d-391c-11dd-90d9-001a92567626'),
        'sender_address_ref' => env('NOVA_POST_SENDER_ADDRESS_REF'),
        'sender_phone' => env('NOVA_POST_SENDER_PHONE'),
        'price_weight' => env('NOVA_POST_PRICE_WEIGHT', '0.5'),
        'price_cost' => env('NOVA_POST_PRICE_COST', '500'),
    ],

    'payparts' => [
        'privatbank' => [
            'base_url' => env('PRIVATBANK_PAYPARTS_BASE_URL', 'https://payparts2.privatbank.ua'),
            'public_url' => env('PRIVATBANK_PAYPARTS_PUBLIC_URL'),
            'create_path' => env('PRIVATBANK_PAYPARTS_CREATE_PATH', '/ipp/v2/payment/create'),
            'payment_path' => env('PRIVATBANK_PAYPARTS_PAYMENT_PATH', '/ipp/v2/payment'),
            'decline_path' => env('PRIVATBANK_PAYPARTS_DECLINE_PATH', '/ipp/v2/payment/decline'),
            'state_path' => env('PRIVATBANK_PAYPARTS_STATE_PATH', '/ipp/v2/payment/state'),
        ],
        'monobank' => [
            'base_url' => env('MONOBANK_PAYPARTS_BASE_URL', 'https://u2-demo-ext.mono.st4g3.com'),
            'public_url' => env('MONOBANK_PAYPARTS_PUBLIC_URL'),
            'create_path' => env('MONOBANK_PAYPARTS_CREATE_PATH', '/api/order/create'),
            'state_path' => env('MONOBANK_PAYPARTS_STATE_PATH', '/api/order/state'),
            'confirm_path' => env('MONOBANK_PAYPARTS_CONFIRM_PATH', '/api/order/confirm'),
            'reject_path' => env('MONOBANK_PAYPARTS_REJECT_PATH', '/api/order/reject'),
            'return_path' => env('MONOBANK_PAYPARTS_RETURN_PATH', '/api/order/return'),
            'validate_path' => env('MONOBANK_PAYPARTS_VALIDATE_PATH', '/api/v2/client/validate'),
            'auto_confirm' => (bool) env('MONOBANK_PAYPARTS_AUTO_CONFIRM', true),
        ],
        'admin_polling_enabled' => (bool) env('ADMIN_PAYPARTS_POLLING_ENABLED', true),
        'admin_polling_interval_ms' => (int) env('ADMIN_PAYPARTS_POLLING_INTERVAL_MS', 60000),
        'admin_polling_batch_size' => (int) env('ADMIN_PAYPARTS_POLLING_BATCH_SIZE', 5),
        'admin_polling_max_age_hours' => (int) env('ADMIN_PAYPARTS_POLLING_MAX_AGE_HOURS', 24),
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
        'order_menu_unit_options' => [
            'ml' => env('CALLCENTER_ORDER_MENU_ML_OPTIONS', '3,5,10,15,20,30'),
        ],
    ],

    'instagram' => [
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'user_id' => env('INSTAGRAM_USER_ID'),
        'cache_ttl' => (int) env('INSTAGRAM_CACHE_TTL', 3600),
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
