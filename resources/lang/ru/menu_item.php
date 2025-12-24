<?php

return [
    'nav' => [
        'navigation_label'   => 'Пункты меню',
        'model_label'        => 'Пункт меню',
        'plural_model_label' => 'Пункты меню',
    ],

    'fields' => [
        'parent_id'        => 'Родитель',
        'title'            => 'Заголовок',
        'link_type'        => 'Тип ссылки',
        'target_id'        => 'Целевая сущность',
        'url'              => 'URL',
        'icon'             => 'Иконка (slug)',
        'is_active'        => 'Активно',
        'auth_only'        => 'Только для авторизованных',
        'sort'             => 'Сортировка',
        'visible_from'     => 'Показывать с',
        'visible_to'       => 'Показывать до',
    ],

    'helpers' => [
        'parent_id'  => 'Оставьте -1 для корневого уровня',
        'icon'       => 'Напр.: footer-phone, footer-mail — по желанию',
        'auth_only'  => 'Показывать пункт только вошедшим пользователям',
    ],

    'link_types' => [
        'page'          => 'Страница',
        'category'      => 'Категория каталога',
        'blog'          => 'Блог (пост)',
        'blog_category' => 'Категория блога',
        'url'           => 'Произвольный URL',
    ],

    'placeholders' => [
        'url'        => 'https://... или /contact',
        'no_parent'  => '— без родителя —',
    ],

    'columns' => [
        'title'      => 'Текст',
        'menu_id'    => 'Меню',
        'parent_id'  => 'Родитель',
        'link_type'  => 'Тип',
        'is_active'  => 'Активно',
        'sort'       => 'Сорт.',
        'updated_at' => 'Обновлено',
    ],

    'filters' => [
        'menu_id'   => 'Меню',
        'is_active' => 'Активность',
    ],
];


