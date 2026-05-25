<?php

return [
    'nav' => [
        'navigation_label' => 'Коллцентр',
        'model_label' => 'Заказ',
        'plural_model_label' => 'Заказы',
    ],
    'list' => [
        'actions' => [
            'sync_catalog' => 'Синхронизировать каталог',
            'sync_orders' => 'Получить новые заказы',
        ],
        'widgets' => [
            'orders_total' => 'Заказов',
            'orders_open' => 'Открытые заказы',
            'avg_price' => 'Средний прайс',
        ],
        'notifications' => [
            'catalog_done_title' => 'Синхронизация каталога завершена',
            'catalog_done_body' => 'Источников: :sources. Обработано: :processed. Создано: :created. Обновлено: :updated. Ошибок: :failed.',
            'orders_done_title' => 'Синхронизация заказов завершена',
            'orders_done_body' => 'Источников: :sources. Проверено заказов: :processed. Новых: :created. Уже были: :updated. Ошибок: :failed. :errors',
        ],
    ],
    'actions' => [
        'menu' => 'Меню',
        'promotions' => 'Акции',
        'print_kitchen' => 'Печать на кухню',
        'duplicate' => 'Дубликат',
    ],
    'notifications' => [
        'promo_removed' => 'Акция снята',
        'promo_applied' => 'Акция применена',
    ],
    'print' => [
        'print' => 'Печать',
        'preview' => 'Предпросмотр чека',
        'kitchen' => [
            'preview_heading' => 'Предпросмотр чека кухни',
            'preview_description' => 'Проверьте содержимое чека и укажите количество дубликатов перед печатью.',
            'copies' => 'Количество дубликатов',
        ],
        'client' => [
            'preview_heading' => 'Предпросмотр клиентского чека',
            'preview_description' => 'Проверьте содержимое чека и укажите количество копий перед печатью.',
        ],
    ],
    'order' => [
        'kitchen_info' => 'Информация для кухни',
        'kitchen_info_placeholder' => 'Общая информация по заказу для поваров',
        'client_info' => 'Информация о клиенте',
        'client_info_placeholder' => 'Информация для курьера во время/после доставки',
        'unit_short' => 'Ед.',
        'qty_short' => 'Кол-во',
        'bonuses_not_used' => 'Бонусы не использовались',
        'change_from' => 'Сдача с',
        'change_exact' => 'Сдача 0,00 грн',
        'change_not_enough' => 'Недостаточно',
        'change_due' => 'Сдача',
        'currency_uah' => 'грн',
        'print_client_receipt' => 'Клиентский чек',
        'print_logistic_receipt' => 'Чек для логиста',
        'print_client_and_logistic_receipt' => 'Клиентский + логиста чек',
    ],
    'pages' => [
        'edit' => [
            'heading' => 'Изменить :number',
        ],
    ],
];
