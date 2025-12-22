<?php

return [
    'nav' => [
        'navigation_label'   => 'Меню',
        'model_label'        => 'Меню',
        'plural_model_label' => 'Меню',
    ],

    'fields' => [
        'slug'          => 'Slug',
        'locale'         => 'Локаль меню',
        'sort'           => 'Сортування',
        'max_depth'      => 'Макс. глибина',
        'is_active'      => 'Активно',
        'title'          => 'Заголовок',
    ],

    'helpers' => [
        'slug'      => 'Напр.: header, footer_left, sidebar_catalog',
        'locale'    => 'Залиште порожнім, якщо меню універсальне (для всіх мов)',
        'max_depth' => '1 — плоске меню; 2 і більше — дозволені підрівні',
    ],

    'columns' => [
        'title'      => 'Назва',
        'slug'       => 'Slug',
        'locale'     => 'Локаль',
        'is_active'  => 'Активно',
        'max_depth'  => 'Глибина',
        'sort'       => 'Сорт.',
        'updated_at' => 'Оновлено',
    ],

    'filters' => [
        'locale'     => 'Локаль',
        'is_active'  => 'Активність',
    ],

    'actions' => [
        'items_tree' => 'Пункти (дерево)',
    ],
];

