<?php

return [
    'nav' => [
        'navigation_group'   => 'Лояльність',
        'navigation_label'   => 'Бонусні рахунки',
        'model_label'        => 'Бонусний рахунок',
        'plural_model_label' => 'Бонусні рахунки',
    ],

    'sections' => [
        'main' => 'Основне',
    ],

    'fields' => [
        'id'            => 'ID',
        'client_id'     => 'ID клієнта',
        'phone'         => 'Телефон',
        'balance'       => 'Баланс бонусів',
    ],

    'columns' => [
        'id'            => 'ID',
        'client_id'     => 'ID клієнта',
        'client'        => 'Клієнт',
        'phone'         => 'Телефон',
        'balance'       => 'Баланс',
        'updated_at'    => 'Оновлено',
    ],

    'actions' => [
        'view'      => 'Перегляд',
        'edit'      => 'Редагувати',
        'delete'    => 'Видалити',
    ],

    'helpers' => [
        'balance'   => 'Баланс рахується за транзакціями і редагується тільки через операції.',
    ],

    'transactions' => [
        'title'                 => 'Транзакції за бонусами',
        'fields' => [
            'type'              => 'Тип',
            'source'            => 'Джерело',
            'amount'            => 'Сума',
            'remaining_amount'  => 'Залишок за нарахуванням',
            'expires_at'        => 'Дійсне до',
            'order_id'          => 'ID замовлення',
            'meta'              => 'Дод. дані',
            'meta_key'          => 'Ключ',
            'meta_value'        => 'Значення',
        ],
        'columns' => [
            'id'                => 'ID',
            'type'              => 'Тип',
            'source'            => 'Джерело',
            'amount'            => 'Сума',
            'remaining_amount'  => 'Залишок',
            'expires_at'        => 'Закінчується',
            'order_id'          => 'ID замовлення',
            'created_at'        => 'Створено',
        ],
        'actions' => [
            'create'    => 'Додати операцію',
            'edit'      => 'Редагувати',
            'delete'    => 'Видалити',
        ],
        'types' => [
            'accrual'       => 'Нарахування',
            'spend'         => 'Списання',
            'expire'        => 'Згоряння',
            'adjustment'    => 'Корекція',
            'reverse'       => 'Реверс',
        ],
        'helpers' => [
            'source'            => 'order, welcome_bonus, manual, system_expire і т.п.',
            'amount'            => '> 0 — нарахування, < 0 — списання/згоряння/реверс.',
            'remaining_amount'  => 'Використовується тільки для нарахувань (FIFO). Можна залишити порожнім для інших типів.',
            'order_id'          => 'Якщо операція пов\'язана з конкретним замовленням.',
            'meta'              => 'Довільні дані (коментар, accrual_id і т.п.).',
        ],
    ],
];

