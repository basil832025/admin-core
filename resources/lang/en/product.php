<?php

return [
    // Navigation and model names
    'nav' => [
        'cluster'            => 'Products',
        'group'              => 'Shop',
        'navigation_label'   => 'Products',
        'model_label'        => 'Product',
        'plural_model_label' => 'Products',
    ],

    // Form tabs
    'tabs' => [
        'main'           => 'Main',
        'images'         => 'Images',
        'characteristics'=> 'Characteristics',
        'seo'            => 'SEO',
        'calculation'    => 'Costing',
        'variations'     => 'Variations',
    ],
    
    'pages' => [
        'list_title'       => 'Products list',
        'create_title'     => 'Create product',
        'edit_title'       => 'Edit product',
        'edit_heading'     => 'Edit product',
        'edit_breadcrumb'  => 'Edit',
        'create_breadcrumb'  => 'Create',
    ],

    'actions' => [
        'save'   => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'create' => 'Create',
        'create_another' => 'Create and create another',
        'import_products_excel' => 'Import products from Excel',
        'apply_import' => 'Apply import',
    ],
    
    // Form sections
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

    // Form fields
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
        'is_promo'          => 'Promo',
        'is_vegan'          => 'Vegan',
        'is_product_of_day' => 'Product of the day',
        'is_spicy'          => 'Spicy',
        'exclude_from_promotions' => 'Exclude from promotions',
        'category_id'       => 'Category',
        'categories'        => 'Additional categories',
        'categories_helper' => 'Optional. You may select one or more.',
        'main_image'        => 'Main image',
        'main_image_small'  => 'Thumbnail',
        'images'            => 'Gallery',
        'code2'             => 'External code',
        // Calculation
        'calculations'      => 'Product costings',
        'calc_name'         => 'Name/Version',
        'valid_from'        => 'From date',
        'valid_to'          => 'Until date',
        'valid_to_helper'   => 'Empty — unlimited',
        'component_product' => 'Component',
        'qty'               => 'Qty',
        'short_desc'        => 'Short description',
        'unit_cost'         => 'Unit cost',
        'row_cost'          => 'Row cost',
        'total_cost'        => 'Total cost',
        'price_for_value'   => 'Price: :value',
        'calc_items'        => 'Items',
    ],

    // Placeholders/messages
    'placeholders' => [
        'no_category_label'   => 'No category',
        'no_category_content' => 'Select a category first',
        'code2_placeholder'   => 'E.g., ABC-123',
    ],

    // Table columns
    'columns' => [
        'title'     => 'Title',
        'category'  => 'Category',
        'price'     => 'Price',
        'in_stock'  => 'In stock',
        'sort'      => 'Sort',
        'is_new'    => 'New',
        'is_hit'    => 'Hit',
        'is_home'   => 'On home',
        'is_promo'  => 'Promo',
        'is_vegan'  => 'Vegan',
        'is_product_of_day' => 'Product of the day',
        'is_spicy'  => 'Spicy',
        'exclude_from_promotions' => 'No promos',
        'quantity'  => 'Quantity',
        'sku'       => 'SKU',
        'updated_at'=> 'Updated',
    ],

    // Table filters
    'filters' => [
        'category'      => 'Category',
        'category_all'  => 'All categories',
        'is_new'        => 'New',
        'is_hit'        => 'Hit',
        'is_home'       => 'On home',
        'is_promo'      => 'Promo',
        'is_vegan'      => 'Vegan',
        'is_product_of_day' => 'Product of the day',
        'is_spicy'      => 'Spicy',
        'exclude_from_promotions' => 'Exclude from promotions',
        'code2'         => 'External code',
    ],

    'badges' => [
        'is_promo'          => 'Promo',
        'is_vegan'          => 'Vegan',
        'is_hit'            => 'Hit',
        'is_product_of_day' => 'Pie of the day',
        'is_spicy'          => 'Spicy',
    ],

    'import' => [
        'image_directory' => 'Photo folder',
        'image_directory_helper' => 'Files are matched by SKU. SKU 1 matches both 1.png and 001.png.',
        'overwrite_images' => 'Overwrite existing photos',
        'preview' => 'Preview',
        'rows_to_import' => 'Rows to import',
        'completed' => 'Import completed',
        'completed_body' => 'Created: :created. Updated: :updated. Skipped: :skipped. Photos added: :images_added. Photos skipped: :images_skipped. Photos missing: :images_missing.',
        'failed' => 'Failed to import Excel',
    ],
];
