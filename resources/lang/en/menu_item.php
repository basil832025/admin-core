<?php

return [
    'nav' => [
        'navigation_label'   => 'Menu items',
        'model_label'        => 'Menu item',
        'plural_model_label' => 'Menu items',
    ],

    'fields' => [
        'parent_id'        => 'Parent',
        'title'            => 'Title',
        'link_type'        => 'Link type',
        'target_id'        => 'Target entity',
        'url'              => 'URL',
        'icon'             => 'Icon (slug)',
        'is_active'        => 'Active',
        'auth_only'        => 'For authorized only',
        'sort'             => 'Sort',
        'visible_from'     => 'Visible from',
        'visible_to'       => 'Visible to',
    ],

    'helpers' => [
        'parent_id'  => 'Leave -1 for root level',
        'icon'       => 'E.g.: footer-phone, footer-mail — optional',
        'auth_only'  => 'Show item only to logged in users',
    ],

    'link_types' => [
        'page'          => 'Page',
        'category'      => 'Catalog category',
        'blog'          => 'Blog (post)',
        'blog_category' => 'Blog category',
        'url'           => 'Custom URL',
    ],

    'placeholders' => [
        'url'        => 'https://... or /contact',
        'no_parent'  => '— no parent —',
    ],

    'columns' => [
        'title'      => 'Text',
        'menu_id'    => 'Menu',
        'parent_id'  => 'Parent',
        'link_type'  => 'Type',
        'is_active'  => 'Active',
        'sort'       => 'Sort',
        'updated_at' => 'Updated',
    ],

    'filters' => [
        'menu_id'   => 'Menu',
        'is_active' => 'Activity',
    ],
];

