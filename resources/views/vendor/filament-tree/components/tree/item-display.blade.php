@props(['title', 'icon', 'description', 'record'])
@php
    use Illuminate\Support\HtmlString;
    $isCustomIcon = is_string($icon) && str_starts_with($icon, 'custom:');
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center flex-1 gap-1']) }}>
    @if ($icon)
        <div class="w-4" @if($record?->catalog_icon_color) style="color: {{ $record->catalog_icon_color }}" @endif>
            @if($isCustomIcon)
                <x-product-category-icon :icon="$icon" style="display:inline-flex;width:16px;height:16px" />
            @else
                <x-dynamic-component :component="$icon" class="w-4 h-4" />
            @endif
        </div>
    @endif

    <div @class(['ml-4 rtl:mr-4' => !$icon, 'flex-1'])>
        <span class="font-semibold">{{ str($title)->sanitizeHtml()->toHtmlString() }}</span>
        @if ($description && (is_string($description) || $description instanceof HtmlString))
            @if (is_string($description))
                <span class="text-gray-500 dark:text-gray-400 text-sm truncate">{{ str($description)->sanitizeHtml()->toHtmlString() }}</span>
            @else
                {!! $description->toHtml() !!}
            @endif
        @endif
    </div>
</div>
