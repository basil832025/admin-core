@php
    $isProductCategoryEdit = request()->is('admin/catalog/products/product-categories/*/edit');
@endphp

@if($isProductCategoryEdit)
    <style>
        .category-product-parent-row > td {
            background: #e0f2fe !important;
        }

        .category-product-child-row > td {
            background: #ffe4e6 !important;
        }
    </style>
@endif
