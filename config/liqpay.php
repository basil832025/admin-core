<?php

return [
    'public_key'  => env('LIQPAY_PUBLIC_KEY', ''),
    'private_key' => env('LIQPAY_PRIVATE_KEY', ''),
    'sandbox'     => (bool) env('LIQPAY_SANDBOX', true),
    'public_base_url' => env('LIQPAY_PUBLIC_BASE_URL'),
];
