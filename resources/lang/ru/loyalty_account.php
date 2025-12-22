<?php

return [
    'nav' => [
        'navigation_group'   => 'Лояльность',
        'navigation_label'   => 'Бонусные счета',
        'model_label'        => 'Бонусный счёт',
        'plural_model_label' => 'Бонусные счета',
    ],

    'sections' => [
        'main' => 'Основное',
    ],

    'fields' => [
        'id'            => 'ID',
        'client_id'     => 'ID клиента',
        'phone'         => 'Телефон',
        'balance'       => 'Баланс бонусов',
    ],

    'columns' => [
        'id'            => 'ID',
        'client_id'     => 'ID клиента',
        'client'        => 'Клиент',
        'phone'         => 'Телефон',
        'balance'       => 'Баланс',
        'updated_at'    => 'Обновлён',
    ],

    'actions' => [
        'view'      => 'Просмотр',
        'edit'      => 'Редактировать',
        'delete'    => 'Удалить',
    ],

    'helpers' => [
        'balance'   => 'Баланс считается по транзакциям и редактируется только через операции.',
    ],

    'transactions' => [
        'title'                 => 'Транзакции по бонусам',
        'fields' => [
            'type'              => 'Тип',
            'source'            => 'Источник',
            'amount'            => 'Сумма',
            'remaining_amount'  => 'Остаток по начислению',
            'expires_at'        => 'Действительно до',
            'order_id'          => 'ID заказа',
            'meta'              => 'Доп. данные',
            'meta_key'          => 'Ключ',
            'meta_value'        => 'Значение',
        ],
        'columns' => [
            'id'                => 'ID',
            'type'              => 'Тип',
            'source'            => 'Источник',
            'amount'            => 'Сумма',
            'remaining_amount'  => 'Остаток',
            'expires_at'        => 'Истекает',
            'order_id'          => 'ID заказа',
            'created_at'        => 'Создано',
        ],
        'actions' => [
            'create'    => 'Добавить операцию',
            'edit'      => 'Редактировать',
            'delete'    => 'Удалить',
        ],
        'types' => [
            'accrual'       => 'Начисление',
            'spend'         => 'Списание',
            'expire'        => 'Сгорание',
            'adjustment'    => 'Корректировка',
            'reverse'       => 'Реверс',
        ],
        'helpers' => [
            'source'            => 'order, welcome_bonus, manual, system_expire и т.п.',
            'amount'            => '> 0 — начисление, < 0 — списание/сгорание/реверс.',
            'remaining_amount'  => 'Используется только для начислений (FIFO). Можно оставить пустым для других типов.',
            'order_id'          => 'Если операция связана с конкретным заказом.',
            'meta'              => 'Произвольные данные (комментарий, accrual_id и т.п.).',
        ],
    ],
];

