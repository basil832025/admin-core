@php
    $visible = array_filter($this->getTable()->getVisibleColumns(), fn ($column, $name) => !in_array($name, ['title_search', 'main_image'], true), ARRAY_FILTER_USE_BOTH);
    $order = ['title', 'sku', 'price', 'old_price', 'discount_percent', 'in_stock', 'is_new', 'is_hit', 'is_home', 'is_promo', 'exclude_from_promotions', 'is_vegan', 'is_product_of_day', 'is_spicy', 'sort', 'quantity'];
    $columns = [];
    foreach ($order as $name) if (isset($visible[$name])) $columns[$name] = $visible[$name];
    foreach ($visible as $name => $column) if (!isset($columns[$name])) $columns[$name] = $column;
    $variants = $this->visibleProductVariants($records);
    $hasVariants = collect($records->all())->contains(fn ($record) => $record->variants_count > 0);
    $shortLabels = ['title'=>'Назва','old_price'=>'Стара','discount_percent'=>'Знижка %','in_stock'=>'Наявн.','is_new'=>'Нов.','is_hit'=>'Хіт','is_home'=>'На гол.','sort'=>'Сорт.','quantity'=>'Залишок'];
@endphp
@if($hasVariants)
    <div class="catalog-compact-tree-actions">
        <button type="button" wire:click="expandPageVariants">Розгорнути всі</button>
        <button type="button" wire:click="collapsePageVariants">Згорнути всі</button>
    </div>
@endif
<div class="catalog-compact-scroll">
<table class="catalog-nested-table catalog-compact-table">
    <thead><tr>
        <th class="catalog-compact-expander"></th>
        <th class="catalog-compact-check"><x-filament-tables::selection.checkbox class="fi-ta-page-checkbox" x-on:click="toggleSelectRecordsOnPage" x-bind:checked="getRecordsOnPage().length && areRecordsSelected(getRecordsOnPage())" label="Вибрати товари" /></th>
        @foreach($columns as $name => $column)
            <th class="catalog-compact-col-{{ str_replace('_', '-', $name) }}">@if($column->isSortable())<button type="button" wire:click="sortTable('{{ $name }}')">{{ $shortLabels[$name] ?? $column->getLabel() }} ↕</button>@else{{ $shortLabels[$name] ?? $column->getLabel() }}@endif</th>
        @endforeach
        <th class="catalog-compact-actions">Дії</th>
    </tr></thead>
    <tbody @if($this->isTableReordering()) x-sortable x-on:end.stop="$wire.reorderTable($event.target.sortable.toArray())" @endif>
        @foreach($records as $record)
            @include('filament.clusters.products.pages.partials.catalog-product-compact-row', ['isChild' => false])
            @foreach($variants->get($record->id, collect()) as $variant)
                @include('filament.clusters.products.pages.partials.catalog-product-compact-row', ['record' => $variant, 'isChild' => true])
            @endforeach
        @endforeach
    </tbody>
</table>
</div>
