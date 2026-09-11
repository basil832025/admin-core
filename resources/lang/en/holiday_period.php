<?php

return [
    'nav' => [
        'navigation_group' => 'Settings',
        'navigation_label' => 'Closed dates',
        'model_label' => 'Closed period',
        'plural_model_label' => 'Closed dates',
    ],
    'sections' => [
        'main' => 'Closed-date period',
    ],
    'fields' => [
        'date_from' => 'Date from',
        'date_to' => 'Date to',
        'comment' => 'Comment',
        'is_active' => 'Active',
    ],
    'columns' => [
        'date_from' => 'From',
        'date_to' => 'To',
        'comment' => 'Comment',
        'is_active' => 'Active',
    ],
    'filters' => [
        'is_active' => 'Active',
    ],
];
