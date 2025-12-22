<?php

return [
    'nav' => [
        'navigation_label'   => 'Variations',
        'model_label'        => 'Variation',
        'plural_model_label' => 'Variations',
    ],

    'fields' => [
        'name'                        => 'Variation name',
        'slug'                        => 'Slug',
        'characteristic_values'       => 'Characteristic values',
        'characteristic'              => 'Characteristic',
        'value'                       => 'Value',
    ],

    'columns' => [
        'name'       => 'Name',
        'slug'      => 'Slug',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
    ],

    'helpers' => [
        'name_example'    => 'For example: 30cm / 900g / 3 people',
        'slug_auto'       => 'Automatically created from name if not filled',
    ],
];

