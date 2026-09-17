@php
    $columns = array_filter($this->getTable()->getVisibleColumns(), fn ($column) => $column->getName() !== 'title_search');
    $variants = $this->visibleProductVariants($records);
@endphp
<table class="catalog-nested-table"
    x-init="$wire.restoreCatalogColumnOrder(localStorage.getItem('catalog-columns-order-{{ auth()->id() }}')); $wire.restoreCatalogColumnVisibility(localStorage.getItem('catalog-columns-visible-{{ auth()->id() }}-{{ $this->catalogView }}'))"
    x-on:catalog-columns-visibility-saved.window="localStorage.setItem('catalog-columns-visible-{{ auth()->id() }}-{{ $this->catalogView }}', JSON.stringify($event.detail.state))">
    <thead><tr>
        <th style="width:24px"></th>
        <th><x-filament-tables::selection.checkbox x-on:click="toggleSelectRecordsOnPage" x-bind:checked="getRecordsOnPage().length && areRecordsSelected(getRecordsOnPage())" label="Вибрати товари" /></th>
        @foreach($columns as $name => $column)
            <th data-column="{{ $name }}" draggable="true" title="Перетащите, чтобы изменить порядок"
                x-on:dragstart="$event.dataTransfer.setData('text/plain', $el.dataset.column)"
                x-on:dragover.prevent
                x-on:drop.prevent="let k = 'catalog-columns-order-{{ auth()->id() }}'; let s = $event.dataTransfer.getData('text/plain'); let t = $el.dataset.column; let n = Array.from($el.parentElement.querySelectorAll('[data-column]')).map(e => e.dataset.column); let o = JSON.parse(localStorage.getItem(k) || '[]').filter(c => n.includes(c)); o = [...o, ...n.filter(c => !o.includes(c))]; let a = o.indexOf(s); let b = o.indexOf(t); if (a < b) b--; o.splice(b, 0, o.splice(a, 1)[0]); localStorage.setItem(k, JSON.stringify(o)); $wire.reorderCatalogColumns(s, t)">
                <span aria-hidden="true" style="cursor:grab;margin-right:.35rem">⠿</span>
                @if($column->isSortable())<button type="button" wire:click="sortTable('{{ $name }}')">{{ $column->getLabel() }} ↕</button>@else{{ $column->getLabel() }}@endif
            </th>
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
