<?php

return [
    'nav' => [
        'navigation_label' => 'Колцентр',
        'model_label' => 'Замовлення',
        'plural_model_label' => 'Замовлення',
    ],
    'list' => [
        'actions' => [
            'sync_catalog' => 'Синхронізувати каталог',
            'sync_orders' => 'Отримати нові замовлення',
        ],
        'widgets' => [
            'orders_total' => 'Замовлень',
            'orders_open' => 'Відкриті замовлення',
            'avg_price' => 'Середній чек',
        ],
        'notifications' => [
            'catalog_done_title' => 'Синхронізацію каталогу завершено',
            'catalog_done_body' => 'Джерел: :sources. Оброблено: :processed. Створено: :created. Оновлено: :updated. Помилок: :failed.',
            'orders_done_title' => 'Синхронізацію замовлень завершено',
            'orders_done_body' => 'Джерел: :sources. Перевірено замовлень: :processed. Нових: :created. Уже були: :updated. Помилок: :failed. :errors',
        ],
    ],
    'actions' => [
        'menu' => 'Меню',
        'promotions' => 'Акції',
        'print_kitchen' => 'Друк на кухню',
        'duplicate' => 'Дублікат',
    ],
    'notifications' => [
        'promo_removed' => 'Акцію знято',
        'promo_applied' => 'Акцію застосовано',
    ],
    'print' => [
        'print' => 'Друк',
        'preview' => 'Попередній перегляд чека',
        'kitchen' => [
            'preview_heading' => 'Попередній перегляд кухонного чека',
            'preview_description' => 'Перевірте вміст чека і вкажіть кількість дублікатів перед друком.',
            'copies' => 'Кількість дублікатів',
        ],
        'client' => [
            'preview_heading' => 'Попередній перегляд клієнтського чека',
            'preview_description' => 'Перевірте вміст чека і вкажіть кількість копій перед друком.',
        ],
    ],
    'order' => [
        'kitchen_info' => 'Інформація для кухні',
        'kitchen_info_placeholder' => 'Загальна інформація по замовленню для кухарів',
        'client_info' => 'Інформація про клієнта',
        'client_info_placeholder' => 'Інформація для курʼєра під час/після доставки',
        'unit_short' => 'Од.',
        'qty_short' => 'К-сть',
        'bonuses_not_used' => 'Бонуси не використовувалися',
        'change_from' => 'Решта з',
        'change_exact' => 'Решта 0,00 грн',
        'change_not_enough' => 'Недостатньо',
        'change_due' => 'Решта',
        'currency_uah' => 'грн',
        'fiscalize_in_cashalot' => 'Фіскалізувати в касі',
        'fiscalize_in_cashalot_help' => 'Доступно тільки для оплати готівкою або POS-терміналом.',
        'send_cashalot_receipt' => 'Відправити чек ПРРО',
        'print_client_receipt' => 'Клієнтський чек',
        'print_logistic_receipt' => 'Чек для логіста',
        'print_client_and_logistic_receipt' => 'Клієнтський + логіста чек',
    ],
    'pages' => [
        'edit' => [
            'heading' => 'Змінити :number',
        ],
    ],
];
