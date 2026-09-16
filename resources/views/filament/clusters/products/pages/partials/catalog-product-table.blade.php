@php
    $columns = array_filter($this->getTable()->getVisibleColumns(), fn ($column) => $column->getName() !== 'title_search');
    $variants = $this->visibleProductVariants($records);
@endphp
<table class="catalog-nested-table">
    <thead><tr>
        <th style="width:24px"></th>
        <th><x-filament-tables::selection.checkbox x-on:click="toggleSelectRecordsOnPage" x-bind:checked="getRecordsOnPage().length && areRecordsSelected(getRecordsOnPage())" label="Вибрати товари" /></th>
        @foreach($columns as $name => $column)
            <th>@if($column->isSortable())<button type="button" wire:click="sortTable('{{ $name }}')">{{ $column->getLabel() }} ↕</button>@else{{ $column->getLabel() }}@endif</th>
        @endforeach
        <th>Дії</th>
    </tr></thead>
    <tbody @if($this->isTableReordering()) x-sortable x-on:end.stop="$wire.reorderTable($event.target.sortable.toArray())" @endif>
        @foreach($records as $record)
            @include('filament.clusters.products.pages.partials.catalog-product-row', ['isChild' => false])
            @foreach($variants->get($record->id, collect()) as $variant)
                @include('filament.clusters.products.pages.partials.catalog-product-row', ['record' => $variant, 'isChild' => true])
            @endforeach
        @endforeach
    </tbody>
</table>
