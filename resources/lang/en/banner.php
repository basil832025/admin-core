<?php

return [
    'nav' => [
        'navigation_label'   => 'Banners',
        'model_label'        => 'Banner',
        'plural_model_label' => 'Banners',
    ],

    'sections' => [
        'main'     => 'Main',
        'images'   => 'Images',
    ],

    'fields' => [
        'title'            => 'Title',
        'subtitle'         => 'Subtitle',
        'button_text'      => 'Button text',
        'url'              => 'Link',
        'target'           => 'Open link',
        'is_active'        => 'Active',
        'sort'             => 'Sort',
        'starts_at'        => 'Show from',
        'ends_at'          => 'Show until',
        'image'            => 'Universal image (desktop)',
        'image_mobile'     => 'Universal image (mobile)',
        'images'           => 'Image for language',
    ],

    'targets' => [
        '_self'  => 'In this window',
        '_blank' => 'In new window',
    ],

    'helpers' => [
        'images_default' => 'Can be left empty — then universal image will be used.',
        'images_other'   => 'If not filled — universal or Ukrainian image will be used.',
    ],

    'columns' => [
        'image'     => 'Banner',
        'title'     => 'Title',
        'url'       => 'Link',
        'is_active' => 'Active',
        'sort'      => 'Sort',
        'starts_at' => 'From',
        'ends_at'   => 'Until',
    ],

    'filters' => [
        'is_active' => 'Active',
    ],
];

