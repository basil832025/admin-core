<?php

return [
    'nav' => [
        'navigation_group' => 'Shop',
        'navigation_label' => 'Bonus expiration history',
        'model_label' => 'Bonus expiration',
        'plural_model_label' => 'Bonus expiration history',
    ],
    'columns' => [
        'created_at' => 'Date',
        'account_id' => 'Account',
        'client_id' => 'Client ID',
        'phone' => 'Phone',
        'amount' => 'Amount',
        'accrual_id' => 'Accrual ID',
        'balance_after' => 'Balance after',
    ],
    'filters' => [
        'quick_period' => 'Quick period',
        'period' => 'Period',
        'today' => 'Today',
        'last_7_days' => '7 days',
        'this_month' => 'This month',
        'from' => 'From',
        'until' => 'Until',
    ],
];
