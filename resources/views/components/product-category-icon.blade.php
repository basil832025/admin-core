@props(['icon', 'color' => null])
@php
    $icon = \App\Support\ProductCategoryIcons::valid($icon);
    $styles = trim((string) $attributes->get('style'));
    if ($color) $styles = rtrim($styles, ';') . ';color:' . $color;
@endphp
<span {{ $attributes->except('style')->class('product-category-svg-icon') }} @if($styles !== '') style="{{ $styles }}" @endif>
    @if(str_starts_with($icon, 'custom:'))
        {!! \App\Support\ProductCategoryIcons::customSvg($icon) !!}
    @else
        <x-dynamic-component :component="$icon" aria-hidden="true" style="width:100%;height:100%" />
    @endif
</span>
