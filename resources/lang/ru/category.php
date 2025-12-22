<?php

return [
    'nav' => [
        'navigation_label'   => 'Категории товаров',
        'model_label'        => 'Категория товаров',
        'plural_model_label' => 'Категории товаров',
    ],

    'tabs' => [
        'main'           => 'Основные',
        'characteristics'=> 'Характеристики',
        'variations'     => 'Вариации',
    ],

    'fields' => [
        'title'            => 'Название',
        'description'      => 'Описание',
        'slug'             => 'Slug',
        'parent_id'        => 'Родительская категория',
        'parent_root'      => 'Корневая категория',
        'is_visible'       => 'Видима пользователям',
        'characteristics'  => 'Характеристики',
        'variations'       => 'Вариации',
        'variation_name'   => 'Название вариации',
    ],

    'placeholders' => [
        'parent_id' => 'Выберите родительскую категорию',
    ],
    'tree' => [
        'nav_label'  => 'Дерево категорий',
        'title'      => 'Категории товаров',
        'breadcrumb' => 'Категории товаров',
    ],
    'columns' => [
        'title'      => 'Название',
        'slug'       => 'Slug',
        'parent'     => 'Родитель',
        'is_visible' => 'Видимость',
        'updated_at' => 'Обновлено',
        'sku' => 'SKU',
    ],

    'misc' => [
        'untitled' => 'Без названия',
    ],
    'actions' => [
        'create' => 'Добавить категорию',
        'edit'   => 'Редактировать',
    ],
];
