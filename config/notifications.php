<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Email Addresses
    |--------------------------------------------------------------------------
    |
    | Email addresses for system notifications (orders, etc.)
    |
    */

    'order_notification_email' => array_filter(
        array_map('trim', explode(',', env('ORDER_NOTIFICATION_EMAIL', 'info@3piroga.ua')))
    ),

];

