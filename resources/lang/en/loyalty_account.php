<?php

return [
    'nav' => [
        'navigation_group'   => 'Loyalty',
        'navigation_label'   => 'Bonus accounts',
        'model_label'        => 'Bonus account',
        'plural_model_label' => 'Bonus accounts',
    ],

    'sections' => [
        'main' => 'Main',
    ],

    'fields' => [
        'id'            => 'ID',
        'client_id'     => 'Client ID',
        'phone'         => 'Phone',
        'balance'       => 'Bonus balance',
    ],

    'columns' => [
        'id'            => 'ID',
        'client_id'     => 'Client ID',
        'client'        => 'Client',
        'phone'         => 'Phone',
        'balance'       => 'Balance',
        'updated_at'    => 'Updated',
    ],

    'actions' => [
        'view'      => 'View',
        'edit'      => 'Edit',
        'delete'    => 'Delete',
    ],

    'helpers' => [
        'balance'   => 'Balance is calculated from transactions and can only be edited through operations.',
    ],

    'transactions' => [
        'title'                 => 'Bonus transactions',
        'fields' => [
            'type'              => 'Type',
            'source'            => 'Source',
            'amount'            => 'Amount',
            'remaining_amount'  => 'Remaining amount',
            'expires_at'        => 'Valid until',
            'order_id'          => 'Order ID',
            'meta'              => 'Additional data',
            'meta_key'          => 'Key',
            'meta_value'        => 'Value',
        ],
        'columns' => [
            'id'                => 'ID',
            'type'              => 'Type',
            'source'            => 'Source',
            'amount'            => 'Amount',
            'remaining_amount'  => 'Remaining',
            'expires_at'        => 'Expires',
            'order_id'          => 'Order ID',
            'created_at'        => 'Created',
        ],
        'actions' => [
            'create'    => 'Add transaction',
            'edit'      => 'Edit',
            'delete'    => 'Delete',
        ],
        'types' => [
            'accrual'       => 'Accrual',
            'spend'         => 'Spend',
            'expire'        => 'Expire',
            'adjustment'    => 'Adjustment',
            'reverse'       => 'Reverse',
        ],
        'helpers' => [
            'source'            => 'order, welcome_bonus, manual, system_expire, etc.',
            'amount'            => '> 0 — accrual, < 0 — spend/expire/reverse.',
            'remaining_amount'  => 'Used only for accruals (FIFO). Can be left empty for other types.',
            'order_id'          => 'If the transaction is related to a specific order.',
            'meta'              => 'Arbitrary data (comment, accrual_id, etc.).',
        ],
    ],
];

