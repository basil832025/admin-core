<?php

return [
    'nav' => [
        'navigation_group'   => 'Discount programs',
        'navigation_label'   => 'Promo codes',
        'model_label'        => 'Promo code',
        'plural_model_label' => 'Promo codes',
    ],

    'sections' => [
        'parameters'    => 'Promo code parameters',
        'scope'         => 'Scope',
        'statistics'    => 'Statistics (read-only)',
    ],

    'fields' => [
        'code'                      => 'Promo code',
        'percent'                   => 'Discount %',
        'is_active'                 => 'Active',
        'starts_at'                 => 'Start date',
        'ends_at'                   => 'End date',
        'max_uses'                  => 'Global usage limit',
        'per_client_limit'          => 'Client usage limit',
        'note'                      => 'Note',
        'categories'                => 'Categories (groups)',
        'products'                  => 'Products',
        'characteristics'           => 'Characteristics (any value)',
        'characteristic_values'     => 'Characteristic values',
        'used_total'                => 'Total uses',
        'remaining'                 => 'Remaining global uses',
    ],

    'columns' => [
        'code'          => 'Code',
        'percent'       => 'Discount',
        'is_active'     => 'Active',
        'starts_at'     => 'Start',
        'ends_at'       => 'End',
        'usages_count'  => 'Uses total',
        'remaining'     => 'Remaining',
    ],

    'filters' => [
        'is_active'     => 'Active',
        'active_now'    => 'Active now',
    ],

    'helpers' => [
        'code_uppercase'            => 'Will be saved in UPPERCASE',
        'max_uses_empty'             => 'Empty = no limit',
        'characteristic_values_hint' => 'First select characteristic(s); only their values will be shown here.',
        'scope_description'         => 'Limit the promo code by groups, products and/or characteristics',
    ],
];

