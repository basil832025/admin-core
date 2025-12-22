<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],


    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'clients',
        ],
        'admin' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],
        'client' => [                     // ← добавить
            'driver'   => 'session',
            'provider' => 'clients',
        ],
    ],

    'providers' => [
        'clients' => [              // МОДЕЛЬ КЛИЕНТОВ
            'driver' => 'eloquent',
            'model'  => App\Models\Shop\Client::class,
        ],
        'users' => [                // МОДЕЛЬ АДМИНОВ (стандартный User)
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'clients' => [
            'provider' => 'clients',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
