<?php

return [
    'nav' => [
        'navigation_label'   => 'Категорії товарів',
        'model_label'        => 'Категорія товарів',
        'plural_model_label' => 'Категорії товарів',
    ],

    'tabs' => [
        'main'           => 'Основні',
        'characteristics'=> 'Характеристики',
        'variations'     => 'Варіації',
    ],

    'fields' => [
        'title'            => 'Назва',
        'description'      => 'Опис',
        'slug'             => 'Slug',
        'parent_id'        => 'Батьківська категорія',
        'parent_root'      => 'Коренева категорія',
        'is_visible'       => 'Видима користувачам',
        'characteristics'  => 'Характеристики',
        'variations'       => 'Варіації',
        'variation_name'   => 'Назва варіації',
    ],

    'placeholders' => [
        'parent_id' => 'Оберіть батьківську категорію',
    ],
    'tree' => [
        'nav_label'  => 'Дерево категорій',
        'title'      => 'Категорії товарів',
        'breadcrumb' => 'Категорії товарів',
    ],
    'columns' => [
        'title'      => 'Назва',
        'slug'       => 'Slug',
        'parent'     => 'Батьківська',
        'is_visible' => 'Видимість',
        'updated_at' => 'Оновлено',
        'sku' => 'SKU',
    ],

    'misc' => [
        'untitled' => 'Без назви',
    ],
    'actions' => [
        'create' => 'Додати категорію',
        'edit'   => 'Редагувати',
    ],
];
