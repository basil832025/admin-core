<tr class="{{ $isChild ? 'catalog-variant-row' : '' }}" wire:key="catalog-product-row-{{ $record->id }}" @if(!$isChild && $this->isTableReordering()) x-sortable-item="{{ $record->id }}" @endif>
    <td>
        @if(!$isChild && $record->variants_count)
            <button type="button" wire:click="toggleProductVariants({{ $record->id }})" class="catalog-product-expand" aria-expanded="{{ in_array($record->id, $this->expandedProducts, true) ? 'true' : 'false' }}" aria-label="Показати або приховати варіанти">
                <x-heroicon-o-chevron-right style="width:18px;height:18px;{{ in_array($record->id, $this->expandedProducts, true) ? 'transform:rotate(90deg)' : '' }}" />
            </button>
        @elseif($isChild)<span class="catalog-variant-branch" aria-hidden="true"></span>@endif
    </td>
    <td>@if(!$isChild)<x-filament-tables::selection.checkbox class="fi-ta-record-checkbox" :value="(string) $record->id" x-model="selectedRecords" :label="'Вибрати товар ' . $record->sku" />@endif</td>
    @foreach($columns as $name => $column)
        <td class="{{ $isChild && $name === 'title' ? 'catalog-variant-title' : '' }}">
            {{ (clone $column)->inline()->record($record)->viewData(['recordKey' => (string) $record->id]) }}
            @if($name === 'title' && !$isChild && $record->variants_count)<small>{{ $record->variants_count }} варіантів · Головний товар</small>@endif
        </td>
    @endforeach
    <td>@if(\App\Filament\Clusters\Products\Resources\ProductResource::canEdit($record))
        <a href="{{ \App\Filament\Clusters\Products\Resources\ProductResource::getUrl('edit', array_filter(['record' => $record, 'source' => 'catalog', 'return_category' => $this->category])) }}" aria-label="Редагувати товар"><x-heroicon-o-pencil-square style="width:18px;height:18px" /></a>
    @endif</td>
</tr>
