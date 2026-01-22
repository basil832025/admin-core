<?php

return [
    'nav' => [
        'navigation_group'   => 'Дисконтні програми',
        'navigation_label'   => 'Фіксовані знижки',
        'model_label'        => 'Фіксована знижка',
        'plural_model_label' => 'Фіксовані знижки',
    ],

    'fields' => [
        'name'          => 'Назва',
        'description'   => 'Опис знижки',
        'percent'       => 'Знижка %',
        'is_active'     => 'Активна',
        'applies_to'    => 'Застосовується до',
        'starts_at'     => 'Початок дії',
        'ends_at'       => 'Закінчення дії',
        'note'          => 'Примітка',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Назва',
        'percent'       => 'Знижка',
        'is_active'     => 'Активна',
        'starts_at'     => 'Початок',
        'ends_at'       => 'Закінчення',
        'created_at'    => 'Створено',
    ],

    'filters' => [
        'is_active'     => 'Активна',
    ],

    'options' => [
        'applies_to_all'    => 'Всі клієнти',
        'applies_to_client' => 'Конкретний клієнт',
        'applies_to_segment'=> 'Сегмент клієнтів',
    ],
];


