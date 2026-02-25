<?php

return [
    'nav' => [
        'navigation_label' => 'Логістика',
        'model_label' => 'Логістика',
        'plural_model_label' => 'Логістика',
    ],
    'columns' => [
        'delivery_address' => 'Адреса доставки',
        'self_pickup' => 'Самовивіз',
    ],
    'actions' => [
        'close' => 'Закрити',
        'route_hint' => 'Натисніть, щоб відкрити маршрут',
        'route_modal_heading' => 'Маршрут до замовлення #:number',
        'destination' => 'Точка доставки',
        'route_loading' => 'Пробуємо побудувати маршрут від вашої геолокації...',
        'open_in_google_maps' => 'Відкрити у Google Maps',
        'route_not_available' => 'Не вдалося побудувати маршрут: перевірте адресу та ключ Google Maps.',
        'route_origin_auto' => 'Маршрут побудовано.',
        'route_origin_kitchen' => 'GPS недоступний, побудовано від точки кухні.',
        'route_origin_manual' => 'GPS і точка кухні недоступні. Вкажіть точку старту у Google Maps.',
        'route_destination_missing' => 'У замовлення немає координат або адреси доставки.',
        'route_origin_gps' => 'Маршрут побудовано від вашої геолокації.',
    ],
];
