@php
    $locale = \App\Models\Setting::value('default_language_code') ?: config('app.locale');
    $title = $record->short_name ?: $record->getTranslation('title', $locale);
    $canEdit = \App\Filament\Clusters\Products\Resources\ProductResource::canEdit($record);
    $matches = $this->compactSearchMatches($record);
@endphp
<tr class="{{ $isChild ? 'catalog-variant-row' : 'catalog-main-row' }} {{ $matches ? 'catalog-search-match' : '' }}" wire:key="catalog-compact-row-{{ $record->id }}" @if(!$isChild && $this->isTableReordering()) x-sortable-item="{{ $record->id }}" @endif>
    <td class="catalog-compact-expander">
        @if(!$isChild && $record->variants_count)
            <button type="button" wire:click="toggleProductVariants({{ $record->id }})" class="catalog-product-expand" aria-expanded="{{ in_array($record->id, $this->expandedProducts, true) ? 'true' : 'false' }}"><x-heroicon-o-chevron-right style="width:15px;height:15px;{{ in_array($record->id, $this->expandedProducts, true) ? 'transform:rotate(90deg)' : '' }}" /></button>
        @elseif($isChild)<span class="catalog-variant-branch" aria-hidden="true"></span>@endif
    </td>
    <td class="catalog-compact-check"><x-filament-tables::selection.checkbox class="fi-ta-record-checkbox" :value="(string) $record->id" x-model="selectedRecords" :label="'Вибрати товар ' . $record->sku" /></td>
    @foreach($columns as $name => $column)
        <td class="catalog-compact-col-{{ str_replace('_', '-', $name) }}">
            @if($name === 'title')
                <div class="catalog-compact-title" title="{{ $title }}"><span>{{ $isChild ? '↳ ' : '' }}{{ $title }}</span>@if(!$isChild && $record->variants_count)<small>{{ $record->variants_count }} варіанти</small>@endif</div>
            @else
                {{ (clone $column)->inline()->record($record)->viewData(['recordKey' => (string) $record->id]) }}
            @endif
        </td>
    @endforeach
    <td class="catalog-compact-actions">
        @if($canEdit)<details><summary title="Дії">⋮</summary><div><a href="{{ \App\Filament\Clusters\Products\Resources\ProductResource::getUrl('edit', array_filter(['record' => $record, 'source' => 'catalog', 'return_category' => $this->category])) }}">Редагувати</a></div></details>@endif
    </td>
</tr>
