<?php

return [
    'nav' => [
        'navigation_label'   => 'Мови',
        'model_label'        => 'Мова',
        'plural_model_label' => 'Мови',
    ],

    'fields' => [
        'name'          => 'Назва мови',
        'code'          => 'Код мови',
        'country_code'  => 'Країна',
        'position'      => 'Порядок',
        'active'        => 'Активен',
    ],

    'columns' => [
        'name'          => 'Мова',
        'code'          => 'Код',
        'country_code'  => 'Країна',
        'position'      => 'Позиція',
        'active'        => 'Активен',
        'updated_at'    => 'Змінено',
    ],

    'helpers' => [
        'position'      => 'Чим менше — тим вище в списку',
    ],
];


