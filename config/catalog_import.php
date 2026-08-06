<?php

return [
    'product_excel_format' => env('PRODUCT_EXCEL_IMPORT_FORMAT'),
    'product_variants_enabled' => (bool) env('PRODUCT_VARIANTS_ENABLED', true),
    'product_admin_feature_flags' => array_values(array_filter(array_map('trim', explode(',', env('PRODUCT_ADMIN_FEATURE_FLAGS', 'is_new,is_hit,is_home,is_promo,is_vegan,is_product_of_day,is_spicy,exclude_from_promotions'))))),
];
