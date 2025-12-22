<?php

return [
    'nav' => [
        'navigation_label'   => 'Menus',
        'model_label'        => 'Menu',
        'plural_model_label' => 'Menus',
    ],

    'fields' => [
        'slug'          => 'Slug',
        'locale'         => 'Menu locale',
        'sort'           => 'Sort',
        'max_depth'      => 'Max depth',
        'is_active'      => 'Active',
        'title'          => 'Title',
    ],

    'helpers' => [
        'slug'      => 'E.g.: header, footer_left, sidebar_catalog',
        'locale'    => 'Leave empty if menu is universal (for all languages)',
        'max_depth' => '1 — flat menu; 2 or more — sub-levels allowed',
    ],

    'columns' => [
        'title'      => 'Title',
        'slug'       => 'Slug',
        'locale'     => 'Locale',
        'is_active'  => 'Active',
        'max_depth'  => 'Depth',
        'sort'       => 'Sort',
        'updated_at' => 'Updated',
    ],

    'filters' => [
        'locale'     => 'Locale',
        'is_active'  => 'Activity',
    ],

    'actions' => [
        'items_tree' => 'Items (tree)',
    ],
];

