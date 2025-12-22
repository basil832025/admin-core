<?php

return [
    'nav' => [
        'navigation_label'   => 'Вариации',
        'model_label'        => 'Вариация',
        'plural_model_label' => 'Вариации',
    ],

    'fields' => [
        'name'                        => 'Название вариации',
        'slug'                        => 'Слаг',
        'characteristic_values'       => 'Значения характеристик',
        'characteristic'              => 'Характеристика',
        'value'                       => 'Значение',
    ],

    'columns' => [
        'name'       => 'Название',
        'slug'      => 'Slug',
        'created_at' => 'Создано',
        'updated_at' => 'Обновлено',
    ],

    'helpers' => [
        'name_example'    => 'Например: 30см / 900г / 3 чел',
        'slug_auto'       => 'Автоматически создаётся из названия, если не заполнен',
    ],
];

