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
        'sort'           => 'Сортировка',
        'max_depth'      => 'Макс. глубина',
        'is_active'      => 'Активно',
        'title'          => 'Заголовок',
    ],

    'helpers' => [
        'slug'      => 'Напр.: header, footer_left, sidebar_catalog',
        'locale'    => 'Оставь пустым, если меню универсальное (для всех языков)',
        'max_depth' => '1 — плоское меню; 2 и более — разрешены подуровни',
    ],

    'columns' => [
        'title'      => 'Название',
        'slug'       => 'Slug',
        'locale'     => 'Локаль',
        'is_active'  => 'Активно',
        'max_depth'  => 'Глубина',
        'sort'       => 'Сорт.',
        'updated_at' => 'Обновлено',
    ],

    'filters' => [
        'locale'     => 'Локаль',
        'is_active'  => 'Активность',
    ],

    'actions' => [
        'items_tree' => 'Пункты (дерево)',
    ],
];


