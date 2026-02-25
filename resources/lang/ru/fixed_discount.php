<?php

return [
    'nav' => [
        'navigation_group'   => 'Настройки',
        'navigation_label'   => 'Фиксированные скидки',
        'model_label'        => 'Фиксированная скидка',
        'plural_model_label' => 'Фиксированные скидки',
    ],

    'fields' => [
        'name'          => 'Название',
        'description'   => 'Описания скидки',
        'percent'       => 'Скидка %',
        'is_active'     => 'Активна',
        'applies_to'    => 'Применяется к',
        'starts_at'     => 'Начало действия',
        'ends_at'       => 'Окончание действия',
        'note'          => 'Примечание',
    ],

    'columns' => [
        'id'            => 'Id',
        'name'          => 'Название',
        'percent'       => 'Скидка',
        'is_active'     => 'Активна',
        'starts_at'     => 'Начало',
        'ends_at'       => 'Окончание',
        'created_at'    => 'Создано',
    ],

    'filters' => [
        'is_active'     => 'Активна',
    ],

    'options' => [
        'applies_to_all'    => 'Все клиенты',
        'applies_to_client' => 'Конкретный клиент',
        'applies_to_segment'=> 'Сегмент клиентов',
    ],
];

