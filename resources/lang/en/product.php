<?php

return [
    'nav' => [
        'navigation_label'   => 'Products',
        'model_label'        => 'Product',
        'plural_model_label' => 'Products',
    ],

    'tabs' => [
        'main'           => 'Main',
        'images'         => 'Images',
        'characteristics'=> 'Characteristics',
        'seo'            => 'SEO',
        'calculation'    => 'Costing',
        'variations'     => 'Variations',
    ],

    'sections' => [
        'prices'        => 'Prices',
        'stock'         => 'Stock',
        'extra'         => 'Additional',
        'props'         => 'Product properties',
        'statuses'      => 'Statuses',
        'associations'  => 'Associations',
        'gallery'       => 'Gallery',
        'calc_versions' => 'Costing versions',
        'calc_items'    => 'Composition (items)',
        'calc_summary'  => 'Summary',
    ],

    'fields' => [
        'title'             => 'Title',
        'description'       => 'Description',
        'price'             => 'Price',
        'old_price'         => 'Old price',
        'sku'               => 'SKU',
        'quantity'          => 'Quantity',
        'dop_info'          => 'Costing / Extra info',
        'short_name'        => 'Short name',
        'slug'              => 'Slug',
        'in_stock'          => 'In stock',
        'is_new'            => 'New',
        'is_hit'            => 'Hit',
        'is_home'           => 'On home',
        'category_id'       => 'Category',
        'categories'        => 'Additional categories',
        'categories_helper' => 'Optional. You may select one or more.',
        'main_image'        => 'Main image',
        'images'            => 'Gallery',
        // Calculation
        'calculations'      => 'Product costings',
        'calc_name'         => 'Name/Version',
        'valid_from'        => 'From date',
        'valid_to'          => 'Until date',
        'valid_to_helper'   => 'Empty — unlimited',
        'component_product' => 'Component',
        'qty'               => 'Qty',
        'unit_cost'         => 'Unit cost',
        'row_cost'          => 'Row cost',
        'total_cost'        => 'Total cost',
        'price_for_value'   => 'Price: :value',
    ],

    'placeholders' => [
        'no_category_label'   => 'No category',
        'no_category_content' => 'Select a category first',
        'code2_placeholder'   => 'E.g., ABC-123',
    ],

    'columns' => [
        'title'     => 'Title',
        'category'  => 'Category',
        'price'     => 'Price',
        'in_stock'  => 'In stock',
        'sort'      => 'Sort',
        'is_new'    => 'New',
        'is_hit'    => 'Hit',
        'is_home'   => 'On home',
        'quantity'  => 'Quantity',
        'updated_at'=> 'Updated',
    ],

    'filters' => [
        'category'      => 'Category',
        'category_all'  => 'All categories',
        'is_new'        => 'New',
        'is_hit'        => 'Hit',
        'is_home'       => 'On home',
        'code2'         => 'External code',
    ],
];
