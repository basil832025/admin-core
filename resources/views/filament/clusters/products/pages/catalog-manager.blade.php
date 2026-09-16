<x-filament-panels::page>
    <style>
        .catalog-manager-grid{display:grid;grid-template-columns:minmax(260px,300px) minmax(0,1fr);gap:1rem}
        .catalog-manager-sidebar,.catalog-manager-products{background:#fff;border:1px solid rgb(226 232 240);border-radius:.75rem;min-width:0}
        .catalog-manager-sidebar{padding:1rem;height:fit-content}
        .catalog-manager-sidebar-heading,.catalog-manager-context-row{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
        .catalog-manager-sidebar h2,.catalog-manager-context h2{font-size:1.05rem;font-weight:700;color:#17213b;margin:0}
        .catalog-manager-icon-button,.catalog-manager-edit{border:1px solid rgb(226 232 240);border-radius:.5rem;padding:.35rem .65rem;color:#334155;text-decoration:none}.catalog-manager-edit{white-space:nowrap;flex-shrink:0;font-size:.875rem}
        .catalog-manager-search{width:100%;margin:1rem 0 .65rem;border:1px solid rgb(226 232 240);border-radius:.5rem;padding:.6rem .75rem}
        .catalog-manager-category{width:100%;display:flex;align-items:center;justify-content:space-between;text-align:left;border:0;background:transparent;border-radius:.5rem;padding:.55rem .6rem;color:#17213b;cursor:pointer}
        .catalog-manager-category:hover{background:#fff7f2}.catalog-manager-category.is-selected{background:#fff0e8;color:#ea4f0b;box-shadow:inset 3px 0 #f4511e}
        .catalog-manager-count,.catalog-manager-badge{font-size:.78rem;color:#64748b;background:#f1f5f9;border-radius:999px;padding:.18rem .5rem;white-space:nowrap}
        .catalog-manager-category.is-selected .catalog-manager-count{color:#c2410c;background:#ffe4d5}
        .catalog-manager-children{padding-left:1rem;border-left:1px solid #e2e8f0;margin-left:.65rem}
        .catalog-manager-chevron,.catalog-manager-chevron-spacer{width:28px;height:28px;flex:0 0 28px}
        .catalog-manager-chevron{display:flex;align-items:center;justify-content:center;border-radius:6px;color:#64748b;cursor:pointer}
        .catalog-manager-chevron:hover{color:#ea4f0b;background:#ffe4d5}.catalog-manager-chevron svg{transition:transform .15s ease}
        .catalog-manager-category-select{display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex:1;min-width:0;text-align:left;padding:0;background:none;border:0;color:inherit;cursor:pointer}
        .catalog-manager-category-label{display:flex;align-items:center;gap:.5rem;min-width:0}.catalog-manager-category-icon{display:inline-flex;width:20px;height:20px;flex:0 0 20px;color:currentColor}.catalog-manager-category-icon svg{width:20px;height:20px;color:currentColor}.catalog-manager-category-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .catalog-manager-chevron:focus-visible,.catalog-manager-category-select:focus-visible{outline:2px solid #f4511e;outline-offset:2px}
        .catalog-manager-add-category{display:block;margin-top:1rem;border:1px dashed #cbd5e1;border-radius:.5rem;padding:.65rem;text-align:center;color:#334155;text-decoration:none}
        .catalog-manager-context{padding:1.1rem 1.25rem;border-bottom:1px solid rgb(226 232 240)}.catalog-manager-breadcrumb{font-size:.85rem;color:#64748b;margin-bottom:.8rem}.catalog-manager-breadcrumb span{padding:0 .4rem}
        .catalog-manager-context h2{font-size:1.35rem}.catalog-manager-context p{margin:.35rem 0 0;color:#64748b;font-size:.85rem}.catalog-manager-badge{font-weight:500;margin-left:.35rem;vertical-align:middle}
        .catalog-manager-table{padding:.25rem .75rem .75rem}.catalog-manager-table .fi-ta-ctn{border:0;box-shadow:none}
        .catalog-manager-table .fi-ta-header-toolbar{gap:.75rem;flex-wrap:wrap;padding:.75rem .5rem}
        .catalog-manager-table .fi-ta-header-toolbar>div:first-of-type{order:2;margin-left:auto}
        .catalog-manager-table .fi-ta-header-toolbar>div:last-of-type{order:1;margin-left:0;gap:.5rem;flex-wrap:wrap}
        .catalog-manager-table .fi-ta-filters-dropdown .fi-dropdown-panel{border-radius:.75rem;box-shadow:0 12px 32px #0f172a20;border:1px solid #e2e8f0}
        .catalog-manager-table .fi-ta-filters{padding:1.1rem;gap:1rem}
        .catalog-manager-table .fi-ta-filters .fi-section{box-shadow:none;border:0;border-radius:0;background:transparent}
        .catalog-manager-table .fi-ta-filters .fi-section-header,.catalog-manager-table .fi-ta-filters .fi-section-content{padding:.4rem 0}
        .catalog-manager-table .fi-ta-filters .fi-section-content{border:0}
        .catalog-manager-table .fi-ta-filters .fi-section-header-heading{font-size:.85rem}
        .catalog-manager-table .fi-ta-filters .fi-fo-toggle-buttons label{font-size:.75rem;padding:.3rem .5rem}
        .catalog-manager-table .fi-ta-filters .fi-fo-component-ctn{gap:1rem}
        .catalog-manager-table .catalog-filter-properties .fi-fo-component-ctn{gap:.5rem}
        .catalog-manager-table .catalog-filter-property>div{grid-template-columns:minmax(0,1fr) auto;column-gap:.5rem;align-items:center}
        .catalog-manager-table .catalog-filter-property>div>div{grid-column:span 1/span 1}
        .catalog-manager-table .catalog-filter-property .fi-fo-field-wrp-label{font-size:.75rem}
        .catalog-manager-table .catalog-filter-property .fi-btn{padding:.25rem .45rem;min-height:28px}
        .catalog-manager-table .catalog-filter-property .fi-btn-label{font-size:.75rem}
        .catalog-manager-table .fi-ta-filters>div:last-child:not(:first-child){display:flex;justify-content:flex-end}
        .catalog-view-switch{display:flex;gap:.4rem;order:3!important;margin-left:auto!important}
        .catalog-view-switch button{display:flex;align-items:center;gap:.4rem;padding:.45rem .65rem;border:1px solid #e2e8f0;border-radius:.5rem;font-size:.85rem;color:#334155;background:#fff;white-space:nowrap}
        .catalog-view-switch button.is-active{background:#f4511e;color:white;border-color:#f4511e}
        .catalog-manager-table .fi-ta-header-toolbar>div:nth-of-type(2){order:2}
        .catalog-product-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:.5rem;padding:.5rem;align-items:start}
        .catalog-product-card{position:relative;border:1px solid #e2e8f0;border-radius:.65rem;overflow:hidden;background:#fff;min-width:0}
        .catalog-card-top{display:flex;justify-content:space-between;position:absolute;z-index:1;top:.5rem;left:.5rem;right:.5rem;pointer-events:none}
        .catalog-card-top>*{pointer-events:auto;background:#fff;border-radius:.3rem;padding:.2rem}
        .catalog-card-photo>div{width:100%!important;height:100px!important;border-radius:0!important}
        .catalog-card-body{padding:.5rem}.catalog-card-title{font-weight:600;font-size:.8rem;min-height:2.25rem}.catalog-card-title div{width:100%!important;max-width:100%!important}
        .catalog-card-sku{font-size:.7rem;color:#64748b;margin:.15rem 0 .35rem}
        .catalog-card-field{display:flex;align-items:center;justify-content:space-between;gap:.35rem;font-size:.72rem;margin:.15rem 0;min-height:20px}
        .catalog-card-field>span{color:#64748b}.catalog-card-field>div{min-width:0}
        .catalog-card-field .fi-ta-toggle{padding:0!important}
        .catalog-card-field .fi-ta-text,.catalog-card-field .fi-ta-icon{padding:0!important}
        .catalog-card-field .fi-ta-text-item-label{font-size:.75rem;line-height:1.25}
        .catalog-card-field .fi-ta-toggle button{transform:scale(.82);transform-origin:right center}
        .catalog-card-price{margin:.35rem 0}.catalog-card-price .fi-ta-text-item-label{font-size:.9rem;font-weight:700}
        .catalog-card-updated{border-top:1px solid #f1f5f9;padding-top:.3rem;margin-top:.35rem;font-size:.65rem}
        .catalog-card-updated .fi-ta-text-item-label{font-size:.65rem}
        .catalog-nested-table{width:100%;border-collapse:collapse;font-size:.85rem}
        .catalog-nested-table th{background:#f8fafc;text-align:left;font-size:.75rem;white-space:nowrap}
        .catalog-nested-table th,.catalog-nested-table td{padding:.4rem .5rem;border-bottom:1px solid #e2e8f0;vertical-align:middle}
        .catalog-nested-table td small{display:block;color:#64748b;font-size:.7rem;margin-top:.15rem}
        .catalog-nested-table .fi-ta-text-input{min-width:90px;max-width:110px}
        .catalog-nested-table .fi-ta-text-input input{font-size:.8rem}
        .catalog-nested-table .fi-ta-toggle{padding:0!important}
        .catalog-variant-row{background:#f8fafc}.catalog-variant-row>td:first-child{position:relative;color:#94a3b8}
        .catalog-variant-branch{position:absolute;left:50%;top:0;bottom:0;border-left:2px solid #dbe3ee}
        .catalog-variant-branch:after{content:'';position:absolute;left:0;top:50%;width:16px;border-top:2px solid #dbe3ee}
        .catalog-variant-row:not(:has(+ .catalog-variant-row)) .catalog-variant-branch{bottom:50%}
        .catalog-variant-row:not(:has(+ .catalog-variant-row)) .catalog-variant-branch:after{top:100%}
        .catalog-variant-title{padding-left:1.5rem!important;color:#475569}
        .catalog-variant-row td:nth-child(3)>div{width:36px!important;height:36px!important}
        .catalog-product-expand{display:flex;align-items:center;justify-content:center;width:24px;height:28px;color:#64748b;border-radius:4px}
        .catalog-product-expand:hover{background:#fff0e8;color:#ea4f0b}
        .catalog-compact-tree-actions{display:flex;justify-content:flex-end;gap:.75rem;padding:.25rem .5rem;font-size:.72rem;color:#475569;border-bottom:1px solid #e2e8f0}
        .catalog-compact-tree-actions button:hover{color:#ea4f0b}
        .catalog-compact-scroll{max-width:100%;overflow-x:auto}
        .catalog-compact-table{width:100%;min-width:1120px;table-layout:auto;font-size:13px}
        .catalog-compact-table th,.catalog-compact-table td{height:38px;padding:2px 6px;white-space:nowrap}
        .catalog-compact-table th{height:32px;font-size:11px;color:#334155}
        .catalog-compact-table .catalog-main-row{background:#fff}.catalog-compact-table .catalog-main-row .catalog-compact-title{font-weight:600}
        .catalog-compact-table .catalog-variant-row{background:#fafcff;color:#475569}.catalog-compact-table .catalog-variant-row td{height:36px}
        .catalog-compact-expander{width:26px;min-width:26px;max-width:26px;padding:0!important}
        .catalog-compact-check{width:30px;min-width:30px;max-width:30px;padding:2px!important}
        .catalog-compact-col-title{width:auto;min-width:250px;max-width:520px}
        .catalog-compact-title{display:flex;align-items:center;gap:.4rem;min-width:0;max-width:100%}
        .catalog-compact-title>span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0}
        .catalog-compact-title small{display:inline-flex!important;margin:0!important;padding:1px 6px;border-radius:999px;background:#f1f5f9;font-size:10px!important;flex:none}
        .catalog-compact-col-sku{width:85px}.catalog-compact-col-price,.catalog-compact-col-old-price{width:92px}.catalog-compact-col-discount-percent{width:66px}
        .catalog-compact-col-in-stock{width:95px}.catalog-compact-col-is-new,.catalog-compact-col-is-hit{width:48px}.catalog-compact-col-is-home{width:58px}
        .catalog-compact-col-sort{width:62px}.catalog-compact-col-quantity{width:62px;text-align:center}.catalog-compact-actions{width:38px;text-align:center;position:relative}
        .catalog-compact-table .fi-ta-text-input{min-width:0;max-width:none;width:100%}.catalog-compact-table .fi-ta-text-input input{height:29px;padding:2px 7px;font-size:12px}
        .catalog-compact-table .catalog-compact-col-discount-percent .fi-ta-text-input{width:62px}.catalog-compact-table .catalog-compact-col-sort .fi-ta-text-input{width:58px}
        .catalog-compact-table .fi-ta-toggle{transform:scale(.78);transform-origin:center;padding:0!important}
        .catalog-compact-table .fi-ta-icon,.catalog-compact-table .fi-ta-text{padding:0!important}
        .catalog-compact-table .catalog-variant-title{padding-left:0!important}
        .catalog-compact-table .catalog-search-match{box-shadow:inset 3px 0 #f4511e;background:#fff7ed}
        .catalog-compact-actions summary{cursor:pointer;list-style:none;font-size:18px;line-height:24px}.catalog-compact-actions summary::-webkit-details-marker{display:none}
        .catalog-compact-actions details>div{position:absolute;right:5px;z-index:20;padding:.35rem;background:#fff;border:1px solid #e2e8f0;border-radius:.4rem;box-shadow:0 8px 20px #0f172a20;text-align:left}
        .catalog-compact-actions a{display:block;padding:.3rem .55rem;color:#334155;text-decoration:none}.catalog-compact-actions a:hover{color:#ea4f0b}
        @media(max-width:900px){.catalog-manager-grid{grid-template-columns:1fr}.catalog-manager-sidebar{max-height:none}.catalog-manager-products{overflow:hidden}.catalog-manager-table{overflow-x:auto}}
    </style>
    <div class="catalog-manager-grid">
        <aside class="catalog-manager-sidebar">
            <div class="catalog-manager-sidebar-heading">
                <h2>Категорії</h2>
                @can('create', \App\Models\Shop\ProductCategory::class)
                    <a class="catalog-manager-icon-button" href="{{ \App\Filament\Clusters\Products\Resources\ProductCategoryResource::getUrl('create', array_filter(['source' => 'catalog', 'return_category' => $category])) }}" title="Додати категорію">+</a>
                @endcan
            </div>
            <input class="catalog-manager-search" type="search" placeholder="Пошук категорії..." wire:model.live.debounce.300ms="categorySearch">
            <button type="button" class="catalog-manager-category {{ $category === null ? 'is-selected' : '' }}" wire:click="selectCategory(null)">
                <span class="catalog-manager-category-label"><x-product-category-icon icon="custom:all-products" class="catalog-manager-category-icon" aria-hidden="true" /><span>Всі товари</span></span><span class="catalog-manager-count">{{ number_format(\App\Models\Shop\Product::query()->parents()->where(fn ($q) => $q->whereNull('is_imported')->orWhere('is_imported', false))->count(), 0, ',', ' ') }}</span>
            </button>
            <div class="catalog-manager-tree">
                @foreach ($this->categoryTree() as $node)
                    @include('filament.clusters.products.pages.partials.catalog-category-node', ['node' => $node, 'depth' => 0])
                @endforeach
            </div>
            @can('create', \App\Models\Shop\ProductCategory::class)
                <a class="catalog-manager-add-category" href="{{ \App\Filament\Clusters\Products\Resources\ProductCategoryResource::getUrl('create', array_filter(['source' => 'catalog', 'return_category' => $category])) }}">＋ Додати категорію</a>
            @endcan
        </aside>
        <section class="catalog-manager-products">
            @php($selected = $this->selectedCategoryRecord())
            <div class="catalog-manager-context">
                <nav class="catalog-manager-breadcrumb" aria-label="Шлях категорії">
                    <button type="button" wire:click="selectCategory(null)">Каталог</button>
                    @forelse($this->categoryBreadcrumbs($selected) as $crumb)
                        <span aria-hidden="true">›</span>
                        @if($loop->last)
                            <span aria-current="page">{{ $crumb->name }}</span>
                        @else
                            <button type="button" wire:click="selectCategory({{ $crumb->id }})">{{ $crumb->name }}</button>
                        @endif
                    @empty
                        <span aria-hidden="true">›</span><span aria-current="page">Всі товари</span>
                    @endforelse
                </nav>
                <div class="catalog-manager-context-row">
                    <div>
                        <h2>{{ $selected?->name ?? 'Всі товари' }} @if($selected)<span class="catalog-manager-badge">{{ $selected->products_count }} товарів</span>@endif</h2>
                    </div>
                    @if($selected && auth('admin')->user()?->can('update', $selected))
                        <a class="catalog-manager-edit" href="{{ \App\Filament\Clusters\Products\Resources\ProductCategoryResource::getUrl('edit', ['record' => $selected, 'source' => 'catalog', 'return_category' => $category]) }}">✎ Редагувати категорію</a>
                    @endif
                </div>
            </div>
            <div class="catalog-manager-table">{{ $this->table }}</div>
        </section>
    </div>
</x-filament-panels::page>
