<?php

return [
    'nav' => [
        'navigation_group' => 'Магазин',
        'navigation_label' => 'История сгорания бонусов',
        'model_label' => 'Сгорание бонусов',
        'plural_model_label' => 'История сгорания бонусов',
    ],
    'columns' => [
        'created_at' => 'Дата',
        'account_id' => 'Счет',
        'client_id' => 'ID клиента',
        'phone' => 'Телефон',
        'amount' => 'Сумма',
        'accrual_id' => 'ID начисления',
        'balance_after' => 'Баланс после',
    ],
    'filters' => [
        'quick_period' => 'Быстрый период',
        'period' => 'Период',
        'today' => 'Сегодня',
        'last_7_days' => '7 дней',
        'this_month' => 'Этот месяц',
        'from' => 'С',
        'until' => 'По',
    ],
];
