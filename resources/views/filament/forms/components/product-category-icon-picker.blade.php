@php
    $icons = \App\Support\ProductCategoryIcons::options();
    $customIcons = \App\Support\ProductCategoryIcons::customOptions();
    $iconColorStatePath = \Illuminate\Support\Str::beforeLast($getStatePath(), '.') . '.icon_color';
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div x-data="{ selected: $wire.entangle('{{ $getStatePath() }}'), iconColor: $wire.entangle('{{ $iconColorStatePath }}') }" x-init="selected = selected || '{{ \App\Support\ProductCategoryIcons::DEFAULT }}'" class="category-icon-picker">
        <div class="category-icon-preview" x-bind:style="{ color: iconColor || '#f4511e' }">
            <span>Попередній перегляд</span>
            @foreach($icons as $icon => $label)
                <span x-show="selected === '{{ $icon }}'" x-cloak title="{{ $label }}">
                    <x-dynamic-component :component="$icon" />
                </span>
            @endforeach
            @foreach($customIcons as $icon => $label)
                <span x-show="selected === '{{ $icon }}'" x-cloak title="{{ $label }}">
                    <x-product-category-icon :icon="$icon" />
                </span>
            @endforeach
        </div>
        @if($customIcons !== [])
            <div class="category-icon-section-title">Іконки каталогу</div>
            <div class="category-icon-grid" role="radiogroup" aria-label="Виберіть іконку каталогу">
                @foreach($customIcons as $icon => $label)
                    <button type="button" role="radio" :aria-checked="selected === '{{ $icon }}'"
                            :class="{ 'is-selected': selected === '{{ $icon }}' }"
                            x-on:click="selected = '{{ $icon }}'" title="{{ $label }}">
                        <x-product-category-icon :icon="$icon" />
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        @endif
        <div class="category-icon-grid" role="radiogroup" aria-label="Виберіть іконку категорії">
            @foreach($icons as $icon => $label)
                <button type="button" role="radio" :aria-checked="selected === '{{ $icon }}'"
                        :class="{ 'is-selected': selected === '{{ $icon }}' }"
                        x-on:click="selected = '{{ $icon }}'" title="{{ $label }}">
                    <x-dynamic-component :component="$icon" />
                    <span>{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>
    <style>
        .category-icon-preview{display:flex;align-items:center;gap:.6rem;margin-bottom:.65rem;font-size:.8rem}.category-icon-preview>span:first-child{color:#64748b}.category-icon-preview svg{width:24px;height:24px}
        .category-icon-section-title{margin:.9rem 0 .45rem;font-size:.8rem;font-weight:600;color:#334155}
        .category-icon-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(92px,1fr));gap:.4rem}
        .category-icon-grid button{display:flex;align-items:center;gap:.4rem;min-height:42px;padding:.4rem .5rem;border:1px solid #e2e8f0;border-radius:.5rem;background:#fff;color:#475569;text-align:left;font-size:.72rem}
        .category-icon-grid button:hover{border-color:#fb923c;color:#ea580c}.category-icon-grid button.is-selected{border-color:#f4511e;background:#fff7ed;color:#ea580c;box-shadow:0 0 0 1px #f4511e}
        .category-icon-grid svg{width:20px;height:20px;flex:0 0 20px}.category-icon-grid .product-category-svg-icon{display:inline-flex;width:20px;height:20px;flex:0 0 20px}.category-icon-grid span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .product-category-svg-icon svg{width:100%;height:100%;color:currentColor}
    </style>
</x-dynamic-component>
