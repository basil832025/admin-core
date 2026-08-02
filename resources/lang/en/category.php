<?php

return [
    'nav' => [
        'navigation_label'   => 'Product categories',
        'model_label'        => 'Product category',
        'plural_model_label' => 'Product categories',
    ],

    'tabs' => [
        'main'           => 'Main',
        'characteristics'=> 'Characteristics',
        'variations'     => 'Variations',
    ],

    'fields' => [
        'title'            => 'Title',
        'description'      => 'Description',
        'slug'             => 'Slug',
        'parent_id'        => 'Parent category',
        'parent_root'      => 'Root category',
        'is_visible'       => 'Visible to users',
        'is_main_group'    => 'Show group as main',
        'is_main_group_help' => 'When enabled, this flag is automatically removed from the other group.',
        'characteristics'  => 'Characteristics',
        'variations'       => 'Variations',
        'variation_name'   => 'Variation name',
    ],

    'placeholders' => [
        'parent_id' => 'Select parent category',
    ],
    
    'tree' => [
        'nav_label'  => 'Category tree',
        'title'      => 'Product categories',
        'main_group_badge' => 'Main group',
        'breadcrumb' => 'Product categories',
    ],
    
    'columns' => [
        'title'      => 'Title',
        'slug'       => 'Slug',
        'parent'     => 'Parent',
        'is_visible' => 'Visibility',
        'is_main_group' => 'Main group',
        'updated_at' => 'Updated',
        'sku'        => 'SKU',
    ],

    'misc' => [
        'untitled' => 'Untitled',
    ],
    
    'actions' => [
        'create' => 'Add category',
        'edit'   => 'Edit',
    ],
];


