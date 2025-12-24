<?php

return [
    'nav' => [
        'navigation_group'   => 'Media',
        'navigation_label'   => 'SVG Images',
        'model_label'        => 'SVG image',
        'plural_model_label' => 'SVG images',
    ],

    'sections' => [
        'main'      => 'Main',
        'colors'    => 'Colors (solid)',
        'svg_code'  => 'SVG code',
        'preview'   => 'Preview',
    ],

    'fields' => [
        'slug'              => 'Name (Latin) / slug',
        'title'             => 'Title',
        'description'       => 'Description',
        'default_color'     => 'Default color',
        'color_variants'    => 'Color variants (presets)',
        'svg_code'          => 'SVG',
        'is_attr'           => 'Use in characteristics',
    ],

    'columns' => [
        'slug'              => 'Slug',
        'title'             => 'Title',
        'svg_preview'       => 'Preview',
        'is_attr'           => 'Used in char.',
        'file_path'         => 'File',
        'updated_at'        => 'Updated',
    ],

    'filters' => [
        'is_attr'           => 'Characteristics only',
    ],

    'filter_options' => [
        'all'               => 'All',
        'yes'               => 'Yes',
        'no'                => 'No',
    ],

    'helpers' => [
        'slug'              => 'Will be the filename: images/svg/{slug}.svg',
        'default_color'     => 'Used if no custom color is specified when displaying.',
        'color_variants'    => 'List HEX values, e.g.: #111827, #FF7500, #EF4444',
        'svg_code'          => 'Paste the full code, starting with &lt;svg ...&gt;',
        'is_attr'           => 'Check if SVG is an icon for product characteristics/properties.',
        'is_attr_tooltip'   => 'Used in characteristics',
        'preview_invalid'   => 'Paste a valid SVG (starts with &lt;svg&gt;)',
        'preview_live'      => 'Live preview',
        'preview_no_color'  => 'no color (will use current text color)',
    ],
];


