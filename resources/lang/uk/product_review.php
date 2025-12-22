<?php

return [
    'nav' => [
        'navigation_label'   => 'Відгуки',
        'model_label'        => 'Відгук',
        'plural_model_label' => 'Відгуки',
    ],

    'fields' => [
        'product'    => 'Продукт',
        'name'       => 'Ім\'я',
        'email'      => 'Email',
        'rating'     => 'Оцінка',
        'review'     => 'Відгук',
        'status'     => 'Статус',
        'ip'         => 'IP',
        'user_agent' => 'User-Agent',
    ],

    'columns' => [
        'product'    => 'Продукт',
        'name'       => 'Ім\'я',
        'email'      => 'Email',
        'rating'     => '★',
        'status'     => 'Статус',
        'created_at' => 'Додано',
    ],

    'actions' => [
        'publish'      => 'Опублікувати',
        'reject'       => 'Відхилити',
        'publish_bulk' => 'Опублікувати вибрані',
        'reject_bulk'  => 'Відхилити вибрані',
    ],

    'filters' => [
        'status' => 'Статус',
    ],
];

