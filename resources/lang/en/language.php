<?php

return [
    'nav' => [
        'navigation_label'   => 'Languages',
        'model_label'        => 'Language',
        'plural_model_label' => 'Languages',
    ],

    'fields' => [
        'name'          => 'Language name',
        'code'          => 'Language code',
        'country_code'  => 'Country',
        'position'      => 'Order',
        'active'        => 'Active',
    ],

    'columns' => [
        'name'          => 'Language',
        'code'          => 'Code',
        'country_code'  => 'Country',
        'position'      => 'Position',
        'active'        => 'Active',
        'updated_at'    => 'Modified',
    ],

    'helpers' => [
        'position'      => 'The smaller — the higher in the list',
    ],
];

