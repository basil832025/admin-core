<?php

return [
    'nav' => [
        'navigation_group' => 'Настройки',
        'navigation_label' => 'Нерабочие дни',
        'model_label' => 'Нерабочий период',
        'plural_model_label' => 'Нерабочие дни',
    ],
    'sections' => [
        'main' => 'Период нерабочих дней',
    ],
    'fields' => [
        'date_from' => 'Дата с',
        'date_to' => 'Дата по',
        'comment' => 'Комментарий',
        'is_active' => 'Активно',
    ],
    'columns' => [
        'date_from' => 'С',
        'date_to' => 'По',
        'comment' => 'Комментарий',
        'is_active' => 'Активно',
    ],
    'filters' => [
        'is_active' => 'Активно',
    ],
];
