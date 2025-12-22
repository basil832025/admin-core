<?php

return [
    'nav' => [
        'navigation_group'   => 'Content',
        'navigation_label'   => 'Article categories',
        'model_label'        => 'Article category',
        'plural_model_label' => 'Article categories',
    ],

    'tabs' => [
        'content' => 'Content',
        'main'    => 'Main',
        'seo'     => 'SEO',
    ],

    'sections' => [
        'additional' => 'Additional',
    ],

    'fields' => [
        'name'             => 'Title',
        'description'      => 'Description',
        'slug'             => 'Slug',
        'sort_order'       => 'Order',
        'is_active'        => 'Active',
        'image'            => 'Image',
        'meta_title'       => 'Meta Title',
        'meta_description' => 'Meta Description',
        'meta_keywords'    => 'Meta Keywords',
    ],

    'columns' => [
        'name'       => 'Name',
        'slug'       => 'Slug',
        'is_active'  => 'Activity',
        'sort_order' => 'Order',
        'updated_at' => 'Updated',
    ],

    'filters' => [
        'is_active' => 'Active',
    ],

    'filter_options' => [
        'yes' => 'Yes',
        'no'  => 'No',
    ],
];

