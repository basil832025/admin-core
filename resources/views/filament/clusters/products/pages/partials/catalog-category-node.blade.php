<div class="catalog-category-node" draggable="true" wire:key="catalog-category-{{ $node->id }}"
     x-data="{ dragging: false, expanded: true }" @dragstart="event.dataTransfer.setData('category', '{{ $node->id }}'); dragging = true" @dragend="dragging = false"
     @dragover.prevent @drop.prevent="let id = event.dataTransfer.getData('category'); if (id && id !== '{{ $node->id }}') $wire.moveCategory(parseInt(id), {{ $node->id }}, event.shiftKey)">
    <div class="catalog-manager-category {{ $category === $node->id ? 'is-selected' : '' }}">
        @if($node->children->isNotEmpty())
            <button type="button" class="catalog-manager-chevron" x-on:click.stop="expanded = !expanded"
                    :aria-expanded="expanded.toString()" aria-controls="catalog-children-{{ $node->id }}"
                    aria-label="Розгорнути або згорнути підкатегорії: {{ $node->name }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     :style="expanded ? 'transform:rotate(90deg)' : 'transform:rotate(0deg)'" aria-hidden="true">
                    <path d="m9 5 7 7-7 7" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        @else
            <span class="catalog-manager-chevron-spacer" aria-hidden="true"></span>
        @endif
        <button type="button" class="catalog-manager-category-select" wire:click="selectCategory({{ $node->id }})">
            <span class="catalog-manager-category-label">
                <x-product-category-icon :icon="$node->catalog_icon" :color="$node->catalog_icon_color" class="catalog-manager-category-icon" aria-hidden="true" />
                <span class="catalog-manager-category-name">{{ $node->name }}</span>
            </span>
            <span class="catalog-manager-count">{{ $node->products_count }}</span>
        </button>
    </div>
    @if($node->children->isNotEmpty())
        <div class="catalog-manager-children" id="catalog-children-{{ $node->id }}" x-show="expanded">
            @foreach($node->children as $child)
                @include('filament.clusters.products.pages.partials.catalog-category-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
