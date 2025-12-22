<?php

return [
    'nav' => [
        'navigation_label'   => 'Языки',
        'model_label'        => 'Язык',
        'plural_model_label' => 'Языки',
    ],

    'fields' => [
        'name'          => 'Название языка',
        'code'          => 'Код языка',
        'country_code'  => 'Страна',
        'position'      => 'Порядок',
        'active'        => 'Активен',
    ],

    'columns' => [
        'name'          => 'Язык',
        'code'          => 'Код',
        'country_code'  => 'Страна',
        'position'      => 'Позиция',
        'active'        => 'Активен',
        'updated_at'    => 'Изменён',
    ],

    'helpers' => [
        'position'      => 'Чем меньше — тем выше в списке',
    ],
];

