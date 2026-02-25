<?php

return [
    'nav' => [
        'navigation_label' => 'Logistics',
        'model_label' => 'Logistics',
        'plural_model_label' => 'Logistics',
    ],
    'columns' => [
        'delivery_address' => 'Delivery address',
        'self_pickup' => 'Pickup',
    ],
    'actions' => [
        'close' => 'Close',
        'route_hint' => 'Click to open route',
        'route_modal_heading' => 'Route for order #:number',
        'destination' => 'Delivery point',
        'route_loading' => 'Trying to build route from your GPS location...',
        'open_in_google_maps' => 'Open in Google Maps',
        'route_not_available' => 'Unable to build route: check destination and Google Maps key.',
        'route_origin_auto' => 'Route is ready.',
        'route_origin_kitchen' => 'GPS is unavailable, route starts from kitchen point.',
        'route_origin_manual' => 'GPS and kitchen point are unavailable. Set start point in Google Maps.',
        'route_destination_missing' => 'Order has no delivery coordinates or address.',
        'route_origin_gps' => 'Route starts from your GPS location.',
    ],
];
