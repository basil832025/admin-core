<?php

return [
    'nav' => [
        'navigation_label'   => 'Варіації',
        'model_label'        => 'Варіація',
        'plural_model_label' => 'Варіації',
    ],

    'fields' => [
        'name'                        => 'Назва варіації',
        'slug'                        => 'Слаг',
        'characteristic_values'       => 'Значення характеристик',
        'characteristic'              => 'Характеристика',
        'value'                       => 'Значення',
    ],

    'columns' => [
        'name'       => 'Назва',
        'slug'      => 'Slug',
        'created_at' => 'Створено',
        'updated_at' => 'Оновлено',
    ],

    'helpers' => [
        'name_example'    => 'Наприклад: 30см / 900г / 3 чол',
        'slug_auto'       => 'Автоматично створюється з назви, якщо не заповнено',
    ],
];

