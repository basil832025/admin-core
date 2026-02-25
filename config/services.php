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
