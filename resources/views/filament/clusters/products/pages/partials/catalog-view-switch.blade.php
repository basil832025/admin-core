<div class="catalog-view-switch" role="group" aria-label="Вигляд каталогу">
    <button type="button" wire:click="setCatalogView('table')" :aria-pressed="$wire.catalogView === 'table'" :class="{ 'is-active': $wire.catalogView === 'table' }">
        <x-heroicon-o-table-cells style="width:16px;height:16px" /> Таблиця
    </button>
    <button type="button" wire:click="setCatalogView('cards')" :aria-pressed="$wire.catalogView === 'cards'" :class="{ 'is-active': $wire.catalogView === 'cards' }">
        <x-heroicon-o-squares-2x2 style="width:16px;height:16px" /> Картки
    </button>
    <button type="button" wire:click="setCatalogView('compact')" :aria-pressed="$wire.catalogView === 'compact'" :class="{ 'is-active': $wire.catalogView === 'compact' }">
        <x-heroicon-o-list-bullet style="width:16px;height:16px" /> Компакт
    </button>
</div>
