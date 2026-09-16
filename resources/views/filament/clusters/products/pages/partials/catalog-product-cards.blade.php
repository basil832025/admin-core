@php($columns = $this->getTable()->getVisibleColumns())
<div class="catalog-product-cards">
    @foreach($records as $record)
        <article class="catalog-product-card" wire:key="catalog-card-{{ $record->id }}">
            <div class="catalog-card-top">
                <x-filament-tables::selection.checkbox :value="(string) $record->id" x-model="selectedRecords" :label="'Вибрати товар ' . $record->sku" />
                @if(\App\Filament\Clusters\Products\Resources\ProductResource::canEdit($record))
                    <a href="{{ \App\Filament\Clusters\Products\Resources\ProductResource::getUrl('edit', array_filter(['record' => $record, 'source' => 'catalog', 'return_category' => $this->category])) }}" aria-label="Редагувати товар" class="catalog-card-edit"><x-heroicon-o-pencil-square style="width:18px;height:18px" /></a>
                @endif
            </div>
            @if(isset($columns['main_image']))
                <div class="catalog-card-photo">{{ $columns['main_image']->record($record) }}</div>
            @endif
            <div class="catalog-card-body">
                @if(isset($columns['title']))<div class="catalog-card-title">{{ $columns['title']->record($record) }}</div>@endif
                @if(isset($columns['sku']))<div class="catalog-card-sku">SKU {{ $record->sku }}</div>@endif
                @foreach($columns as $name => $column)
                    @continue(in_array($name, ['main_image', 'title', 'title_search', 'sku'], true))
                    <div class="catalog-card-field {{ $name === 'price' ? 'catalog-card-price' : '' }} {{ $name === 'updated_at' ? 'catalog-card-updated' : '' }}">
                        <span>{{ $column->getLabel() }}</span>
                        @if($column instanceof \Filament\Tables\Columns\ToggleColumn && ! \App\Filament\Clusters\Products\Resources\ProductResource::canEdit($record))
                            <span>{{ $record->{$name} ? 'Так' : 'Ні' }}</span>
                        @else
                            <div>{{ (clone $column)->inline()->record($record)->viewData(['recordKey' => (string) $record->id]) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    @endforeach
</div>
