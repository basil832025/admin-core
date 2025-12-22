<?php

return [
    'nav' => [
        'navigation_group'   => 'Shop',
        'navigation_label'   => 'Characteristics',
        'model_label'        => 'Characteristic',
        'plural_model_label' => 'Characteristics',
    ],

    'fields' => [
        'name'                      => 'Name',
        'slug'                      => 'Slug',
        'category'                  => 'Category',
        'icon'                      => 'Icon (SVG)',
        'icon_preview'              => 'Icon preview',
        'pricing_type'              => 'Pricing type',
        'sort_position'             => 'Sort position',
        'expand_all_values'         => 'Expand all values',
        'is_required'               => 'Required',
        'show_on_main_tab'          => 'Show on main tab of product',
        'field_type'                => 'Field type',
        'is_active'                 => 'Activity',
    ],

    'columns' => [
        'name'          => 'Name',
        'slug'          => 'Slug',
        'category'      => 'Category',
        'field_type'    => 'Field type',
        'pricing_type'  => 'Pricing',
        'icon'          => 'Icon',
        'is_required'   => 'Required',
        'is_main_tab'   => 'On main',
        'is_active'     => 'Active',
        'sort_order'    => 'Position',
    ],

    'sections' => [
        'main' => 'Main',
        'icon' => 'Icon',
    ],

    'pricing_types' => [
        'no_impact'    => 'No impact',
        'surcharge'    => 'Surcharge',
        'fixed'        => 'Fixed',
    ],

    'field_types' => [
        'text'        => 'TextInput',
        'datetime'    => 'Date/Time',
        'number'      => 'Number',
        'decimal'     => 'Decimal',
        'textarea'    => 'Textarea',
        'toggle'      => 'Switch',
        'color'       => 'Color Picker',
        'file'        => 'File/Image',
        'select'      => 'Select (single)',
        'radio'       => 'RadioList',
        'multiselect' => 'MultiSelect',
        'checkbox'    => 'CheckboxList',
    ],

    'helpers' => [
        'icon_hint'           => 'Choose only from SVG marked as "Use in characteristics".',
        'icon_not_selected'   => 'Icon not selected',
        'icon_load_error'     => 'Failed to load SVG',
        'icon_invalid'        => 'Invalid SVG',
    ],

    'values' => [
        'label'      => 'Values',
        'value'      => 'Value',
        'color'      => 'Color',
        'sort_order' => 'Position',
        'is_active'  => 'Active',
    ],
];

