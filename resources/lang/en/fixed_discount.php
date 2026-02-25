<?php

return [
    'nav' => [
        'navigation_group'   => 'Settings',
        'navigation_label'   => 'Fixed discounts',
        'model_label'        => 'Fixed discount',
        'plural_model_label' => 'Fixed discounts',
    ],

    'fields' => [
        'name'          => 'Name',
        'description' => 'Discount description',
        'percent'       => 'Discount %',
        'is_active'     => 'Active',
        'applies_to'    => 'Applies to',
        'starts_at'     => 'Start date',
        'ends_at'       => 'End date',
        'note'          => 'Note',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Name',
        'percent'       => 'Discount',
        'is_active'     => 'Active',
        'starts_at'     => 'Start',
        'ends_at'       => 'End',
        'created_at'    => 'Created',
    ],

    'filters' => [
        'is_active'     => 'Active',
    ],

    'options' => [
        'applies_to_all'    => 'All clients',
        'applies_to_client' => 'Specific client',
        'applies_to_segment'=> 'Client segment',
    ],
];

