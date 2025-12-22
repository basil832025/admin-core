<?php

return [
    'nav' => [
        'navigation_label'   => 'Отзывы',
        'model_label'        => 'Отзыв',
        'plural_model_label' => 'Отзывы',
    ],

    'fields' => [
        'product'    => 'Продукт',
        'name'       => 'Имя',
        'email'      => 'Email',
        'rating'     => 'Оценка',
        'review'     => 'Отзыв',
        'status'     => 'Статус',
        'ip'         => 'IP',
        'user_agent' => 'User-Agent',
    ],

    'columns' => [
        'product'    => 'Продукт',
        'name'       => 'Имя',
        'email'      => 'Email',
        'rating'     => '★',
        'status'     => 'Статус',
        'created_at' => 'Добавлен',
    ],

    'actions' => [
        'publish'      => 'Опубликовать',
        'reject'       => 'Отклонить',
        'publish_bulk' => 'Опубликовать выбранные',
        'reject_bulk'  => 'Отклонить выбранные',
    ],

    'filters' => [
        'status' => 'Статус',
    ],
];

