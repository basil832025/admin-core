<?php

return [
    'nav' => [
        'navigation_group'   => 'Discount programs',
        'navigation_label'   => 'Time-based discounts',
        'model_label'        => 'Time-based discount',
        'plural_model_label' => 'Time-based discounts',
    ],

    'sections' => [
        'main'      => 'Main parameters',
        'scope'     => 'Scope',
    ],

    'fields' => [
        'name'              => 'Name',
        'is_active'         => 'Active',
        'days'              => 'Weekdays',
        'time_from'         => 'Time from',
        'time_to'           => 'Time to',
        'time_type'         => 'Time type',
        'nth_item'          => 'Every N-th item',
        'percent'           => 'Discount %',
        'starts_at'         => 'Start date',
        'ends_at'           => 'End date',
        'note'              => 'Note',
        'categories'        => 'Categories (groups)',
        'products'          => 'Products',
        'characteristics'   => 'Characteristics (any value)',
        'characteristic_values' => 'Characteristic values',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Name',
        'percent'       => 'Discount',
        'nth_item'      => 'Every N-th',
        'time_type'     => 'Time type',
        'days'          => 'Days',
        'time_from'     => 'From',
        'time_to'       => 'To',
        'is_active'     => 'Active',
        'starts_at'     => 'Start',
        'ends_at'       => 'End',
    ],

    'filters' => [
        'is_active'     => 'Active',
    ],

    'options' => [
        'weekdays' => [
            1 => 'Mon',
            2 => 'Tue',
            3 => 'Wed',
            4 => 'Thu',
            5 => 'Fri',
            6 => 'Sat',
            7 => 'Sun',
        ],
        'time_type_order_created'   => 'By order creation time',
        'time_type_order_fulfilled' => 'By fulfillment time (delivery/pickup)',
        'time_type_display_created' => 'Order creation',
        'time_type_display_fulfilled'=> 'Fulfillment',
        'days_all'                   => 'All',
    ],

    'helpers' => [
        'days'                      => 'If not selected — applies every day',
        'characteristic_values'     => 'If filled — takes priority over just "characteristics (any value)"',
        'scope_description'         => 'Limit the promotion by groups, products, characteristics or their values',
    ],
];


