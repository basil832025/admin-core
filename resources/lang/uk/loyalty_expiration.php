<?php

return [
    'nav' => [
        'navigation_group' => 'Магазин',
        'navigation_label' => 'Історія згоряння бонусів',
        'model_label' => 'Згоряння бонусів',
        'plural_model_label' => 'Історія згоряння бонусів',
    ],
    'columns' => [
        'created_at' => 'Дата',
        'account_id' => 'Рахунок',
        'client_id' => 'ID клієнта',
        'phone' => 'Телефон',
        'amount' => 'Сума',
        'accrual_id' => 'ID нарахування',
        'balance_after' => 'Баланс після',
    ],
    'filters' => [
        'quick_period' => 'Швидкий період',
        'period' => 'Період',
        'today' => 'Сьогодні',
        'last_7_days' => '7 днів',
        'this_month' => 'Цей місяць',
        'from' => 'З',
        'until' => 'По',
    ],
];
