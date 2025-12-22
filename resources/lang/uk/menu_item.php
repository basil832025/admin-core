<?php

return [
    'nav' => [
        'navigation_label'   => 'Пункти меню',
        'model_label'        => 'Пункт меню',
        'plural_model_label' => 'Пункти меню',
    ],

    'fields' => [
        'parent_id'        => 'Батьківський',
        'title'            => 'Заголовок',
        'link_type'        => 'Тип посилання',
        'target_id'        => 'Цільова сутність',
        'url'              => 'URL',
        'icon'             => 'Іконка (slug)',
        'is_active'        => 'Активно',
        'auth_only'        => 'Тільки для авторизованих',
        'sort'             => 'Сортування',
        'visible_from'     => 'Показувати з',
        'visible_to'       => 'Показувати до',
    ],

    'helpers' => [
        'parent_id'  => 'Залиште -1 для кореневого рівня',
        'icon'       => 'Напр.: footer-phone, footer-mail — за бажанням',
        'auth_only'  => 'Показувати пункт тільки увійшовшим користувачам',
    ],

    'link_types' => [
        'page'          => 'Сторінка',
        'category'      => 'Категорія каталогу',
        'blog'          => 'Блог (пост)',
        'blog_category' => 'Категорія блогу',
        'url'           => 'Довільний URL',
    ],

    'placeholders' => [
        'url'        => 'https://... або /contact',
        'no_parent'  => '— без батьківського —',
    ],

    'columns' => [
        'title'      => 'Текст',
        'menu_id'    => 'Меню',
        'parent_id'  => 'Батьківський',
        'link_type'  => 'Тип',
        'is_active'  => 'Активно',
        'sort'       => 'Сорт.',
        'updated_at' => 'Оновлено',
    ],

    'filters' => [
        'menu_id'   => 'Меню',
        'is_active' => 'Активність',
    ],
];

