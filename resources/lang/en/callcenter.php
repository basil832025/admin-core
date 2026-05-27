<?php

return [
    'nav' => [
        'navigation_label' => 'Callcenter',
        'model_label' => 'Order',
        'plural_model_label' => 'Orders',
    ],
    'list' => [
        'actions' => [
            'sync_catalog' => 'Sync catalog',
            'sync_orders' => 'Fetch new orders',
        ],
        'widgets' => [
            'orders_total' => 'Orders',
            'orders_open' => 'Open orders',
            'avg_price' => 'Average check',
        ],
        'notifications' => [
            'catalog_done_title' => 'Catalog sync completed',
            'catalog_done_body' => 'Sources: :sources. Processed: :processed. Created: :created. Updated: :updated. Failed: :failed.',
            'orders_done_title' => 'Order sync completed',
            'orders_done_body' => 'Sources: :sources. Checked orders: :processed. New: :created. Existing: :updated. Failed: :failed. :errors',
        ],
    ],
    'actions' => [
        'menu' => 'Menu',
        'promotions' => 'Promotions',
        'print_kitchen' => 'Print to kitchen',
        'duplicate' => 'Duplicate',
    ],
    'notifications' => [
        'promo_removed' => 'Promotion removed',
        'promo_applied' => 'Promotion applied',
    ],
    'print' => [
        'print' => 'Print',
        'preview' => 'Receipt preview',
        'kitchen' => [
            'preview_heading' => 'Kitchen receipt preview',
            'preview_description' => 'Check the receipt content and set duplicate count before printing.',
            'copies' => 'Duplicate count',
        ],
        'client' => [
            'preview_heading' => 'Client receipt preview',
            'preview_description' => 'Check the receipt content and set number of copies before printing.',
        ],
    ],
    'order' => [
        'kitchen_info' => 'Kitchen information',
        'kitchen_info_placeholder' => 'General kitchen note for this order',
        'client_info' => 'Client information',
        'client_info_placeholder' => 'Information for courier during/after delivery',
        'unit_short' => 'Unit',
        'qty_short' => 'Qty',
        'bonuses_not_used' => 'Bonuses were not used',
        'change_from' => 'Change from',
        'change_exact' => 'Change 0.00 UAH',
        'change_not_enough' => 'Not enough',
        'change_due' => 'Change',
        'currency_uah' => 'UAH',
        'fiscalize_in_cashalot' => 'Fiscalize in cashdesk',
        'fiscalize_in_cashalot_help' => 'Available only for cash or POS terminal payments.',
        'send_cashalot_receipt' => 'Send PRRO receipt',
        'print_client_receipt' => 'Client receipt',
        'print_logistic_receipt' => 'Logistics receipt',
        'print_client_and_logistic_receipt' => 'Client + logistics receipt',
    ],
    'pages' => [
        'edit' => [
            'heading' => 'Edit :number',
        ],
    ],
];
