<?php

return [
    'nav' => [
        'navigation_label'   => 'Currencies',
        'model_label'         => 'Currency',
        'plural_model_label' => 'Currencies',
    ],

    'fields' => [
        'name'      => 'Currency name',
        'code'      => 'Currency code',
        'position'  => 'Sort position',
        'active'    => 'Active',
    ],

    'columns' => [
        'name'          => 'Currency name',
        'code'          => 'Currency code',
        'position'      => 'Sort position',
        'active'        => 'Active',
        'created_at'    => 'Created',
        'updated_at'    => 'Modified',
    ],
];


