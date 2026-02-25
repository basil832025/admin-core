<?php

return [
    'nav' => [
        'navigation_label' => 'Логистика',
        'model_label' => 'Логистика',
        'plural_model_label' => 'Логистика',
    ],
    'columns' => [
        'delivery_address' => 'Адрес доставки',
        'self_pickup' => 'Самовывоз',
    ],
    'actions' => [
        'close' => 'Закрыть',
        'route_hint' => 'Нажмите, чтобы открыть маршрут',
        'route_modal_heading' => 'Маршрут к заказу #:number',
        'destination' => 'Точка доставки',
        'route_loading' => 'Пробуем построить маршрут от вашей геолокации...',
        'open_in_google_maps' => 'Открыть в Google Maps',
        'route_not_available' => 'Не удалось построить маршрут: проверьте адрес и ключ Google Maps.',
        'route_origin_auto' => 'Маршрут построен.',
        'route_origin_kitchen' => 'GPS недоступен, построено от точки кухни.',
        'route_origin_manual' => 'GPS и точка кухни недоступны. Укажите точку старта в Google Maps.',
        'route_destination_missing' => 'У заказа нет координат или адреса доставки.',
        'route_origin_gps' => 'Маршрут построен от вашей геолокации.',
    ],
];
