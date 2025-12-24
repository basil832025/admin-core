<?php

return [
    'nav' => [
        'navigation_group'   => 'Loyalty',
        'navigation_label'   => 'Bonus rules',
        'model_label'        => 'Bonus rule',
        'plural_model_label' => 'Bonus rules',
    ],

    'sections' => [
        'valid_period'      => 'Valid period',
        'regular_bonuses'   => 'Regular bonus accrual',
        'welcome_bonus'     => 'Welcome bonus',
        'other'             => 'Other',
    ],

    'fields' => [
        'valid_from'                    => 'Valid from date',
        'earn_percent'                  => 'Percentage of check',
        'earn_expire_days'              => 'Bonus lifetime (days)',
        'min_order_sum_for_earn'        => 'Min. order amount for accrual',
        'welcome_bonus_amount'          => 'Welcome bonus amount',
        'welcome_bonus_expire_days'     => 'Welcome bonus lifetime (days)',
        'is_enabled'                    => 'Program enabled',
    ],

    'columns' => [
        'valid_from'                    => 'Valid from',
        'is_enabled'                    => 'On',
        'earn_percent'                  => '% of check',
        'earn_expire_days'              => 'Bonus term, days',
        'welcome_bonus_amount'          => 'Welcome bonus',
        'welcome_bonus_expire_days'     => 'Welcome term, days',
        'min_order_sum_for_earn'       => 'Min. order amount',
    ],

    'actions' => [
        'edit'          => 'Edit',
        'delete'        => 'Delete',
        'delete_bulk'   => 'Delete selected',
    ],

    'helpers' => [
        'valid_from'                    => 'The rule takes effect from this date. Only one rule can exist per date.',
        'earn_percent'                   => 'What percentage of the order amount to accrue as bonuses.',
        'earn_expire_days'               => 'How many days after accrual regular bonuses expire.',
        'min_order_sum_for_earn'         => 'If empty — we accrue from any amount.',
        'welcome_bonus_amount'           => 'Accrued to an authorized client on the first paid order.',
        'is_enabled'                     => 'If disabled — bonuses will not be accrued according to this rule.',
    ],
];


