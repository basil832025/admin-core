<?php

namespace App\Filament\Clusters\Products\Pages;

use App\Filament\Clusters\Products;
use App\Filament\Clusters\Products\Resources\ProductPriceChangeBatchResource;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Filament\Clusters\Products\Resources\ProductCategoryResource\RelationManagers\ProductRelationManager;
use App\Filament\Clusters\Products\Resources\ProductResource\Pages\ListProducts;
use App\Models\Shop\ProductCategory;
use App\Models\Shop\Product;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class CatalogManager extends ListProducts
{
    protected static ?string $title = 'Каталог товарів';
    protected static ?string $slug = 'catalog-manager';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?int $navigationSort = 0;
    protected static ?string $cluster = Products::class;
    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    #[Url(as: 'category', history: true)]
    public ?int $category = null;

    public string $categorySearch = '';
    public string $catalogView = 'table';
    public array $expandedProducts = [];
    public array $catalogColumnOrder = [];
    protected ?Collection $catalogCategories = null;

    public function setCatalogView(string $view): void
    {
        if (in_array($view, ['table', 'cards', 'compact'], true) && $view !== $this->catalogView) {
            $this->updatedToggledTableColumns();
            $this->catalogView = $view;
            session()->put('catalog.view.' . Filament::auth()->id(), $view);
            $this->toggledTableColumns = session()->get($this->getTableColumnToggleFormStateSessionKey(), $this->getDefaultTableColumnToggleState());
            $this->getTableColumnToggleForm()->fill($this->toggledTableColumns);
        }
    }

    public function mount(): void
    {
        $view = session()->get('catalog.view.' . Filament::auth()->id(), 'table');
        $this->catalogView = in_array($view, ['table', 'cards', 'compact'], true) ? $view : 'table';
        parent::mount();

        $this->catalogColumnOrder = $this->getSavedCatalogColumnOrder();

        $this->category = $this->category > 0 ? $this->category : null;
        $this->syncCategoryFilter();
    }

    public static function getNavigationLabel(): string
    {
        return 'Каталог товарів';
    }

    public static function getRouteName(?string $panel = null): string
    {
        return Filament::getPanel($panel ?? 'admin')
            ->generateRouteName(Products::prependClusterRouteBaseName('pages.catalog-manager'));
    }

    public static function getUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null): string
    {
        return route(static::getRouteName($panel), $parameters, $isAbsolute);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return ProductResource::canAccess();
    }

    public function getBreadcrumb(): string
    {
        return 'Каталог товарів';
    }

    public function getView(): string
    {
        return 'filament.clusters.products.pages.catalog-manager';
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        // Keep the resource's registered modal actions, but render their buttons in the page header.
        $table->headerActions([]);
        $table->paginationPageOptions([25, 50, 100]);
        $table->content(fn () => view($this->catalogView === 'cards'
            ? 'filament.clusters.products.pages.partials.catalog-product-cards'
            : ($this->catalogView === 'compact'
                ? 'filament.clusters.products.pages.partials.catalog-product-compact'
                : 'filament.clusters.products.pages.partials.catalog-product-table')));

        $columns = $table->getColumns();
        $columns['sort'] = \Filament\Tables\Columns\TextInputColumn::make('sort')
            ->label(__('product.columns.sort'))->type('number')->rules(['required', 'integer', 'min:0'])->sortable()
            ->disabled(fn (Product $record): bool => ! ProductResource::canEdit($record));
        foreach (ProductRelationManager::priceColumns(inheritVariantDiscount: true) as $column) {
            $column->disabled(fn (Product $record): bool => ! ProductResource::canEdit($record));
            $columns[$column->getName()] = $column;
        }
        $table->columns(array_values($columns));

        $table->getFilter('category')->query(function (Builder $query, array $data): Builder {
            $categoryId = (int) ($data['value'] ?? 0);
            if ($categoryId <= 0) {
                return $query;
            }
            $ids = $this->categoryBranchIds($categoryId);
            return $query->where(fn (Builder $products) => $products->whereIn('category_id', $ids)
                ->orWhereHas('categories', fn (Builder $categories) => $categories->whereIn('bs_product_categories.id', $ids)));
        });

        foreach ($table->getColumns() as $column) {
            if ($column->getName() === 'title_search') {
                continue;
            }

            if ($column->getName() === 'title') {
                $column->view('filament.tables.columns.catalog-product-title');
            }

            $column->toggleable(isToggledHiddenByDefault: ! in_array($column->getName(), [
                'sku', 'title', 'price', 'price_unit_label', 'old_price', 'discount_percent', 'in_stock', 'is_new', 'is_hit', 'is_home', 'is_dont_forget', 'is_recommended', 'sort',
            ], true));
            if ($column instanceof \Filament\Tables\Columns\ToggleColumn) {
                $column->disabled(fn (Product $record): bool => ! ProductResource::canEdit($record));
            }
        }

        $featureFilters = [];
        foreach (ProductResource::enabledProductFeatureFlags() as $flag) {
            $label = __(ProductResource::productFeatureDefinitions()[$flag]['filter_label']);
            $featureFilters[$flag] = Filter::make($flag)
                ->visible(fn (): bool => ! $this->isTableColumnToggledHidden($flag))
                ->form([
                    ToggleButtons::make('value')->label($label)->inlineLabel()->grouped()
                        ->extraFieldWrapperAttributes(['class' => 'catalog-filter-property'])
                        ->options(['any' => 'Будь-які', '1' => 'Так', '0' => 'Ні'])
                        ->colors(['any' => 'gray', '1' => 'primary', '0' => 'primary'])
                        ->default('any'),
                ])
                ->query(fn (Builder $query, array $data): Builder => in_array((string) ($data['value'] ?? 'any'), ['1', '0'], true)
                    ? $query->where($flag, (int) $data['value']) : $query)
                ->indicateUsing(fn (array $data): array => in_array((string) ($data['value'] ?? 'any'), ['1', '0'], true)
                    ? [Indicator::make($label . ': ' . ((string) $data['value'] === '1' ? 'Так' : 'Ні'))->removeField('value')] : []);
        }

        $table->filters([
            $table->getFilter('category'),
            Filter::make('availability')->form([
                Radio::make('value')->label('Наявність')->options([
                    'any' => 'Усі', '1' => 'В наявності', '0' => 'Немає в наявності',
                ])->default('any'),
            ])->query(fn (Builder $query, array $data): Builder => in_array((string) ($data['value'] ?? 'any'), ['1', '0'], true)
                ? $query->where('in_stock', (int) $data['value']) : $query)
                ->visible(fn (): bool => ! $this->isTableColumnToggledHidden('in_stock'))
                ->indicateUsing(fn (array $data): array => in_array((string) ($data['value'] ?? 'any'), ['1', '0'], true)
                    ? [Indicator::make((string) $data['value'] === '1' ? 'В наявності' : 'Немає в наявності')->removeField('value')] : []),
            Filter::make('price_range')->form([
                Grid::make(2)->schema([
                    TextInput::make('min')->label('Від')->numeric()->minValue(0)->suffix('₴'),
                    TextInput::make('max')->label('До')->numeric()->minValue(0)->suffix('₴'),
                ]),
            ])->query(function (Builder $query, array $data): Builder {
                foreach (['min' => '>=', 'max' => '<='] as $field => $operator) {
                    if (is_numeric($data[$field] ?? null) && (float) $data[$field] >= 0) {
                        $query->where('price', $operator, (float) $data[$field]);
                    }
                }
                return $query;
            })->visible(fn (): bool => ! $this->isTableColumnToggledHidden('price'))
                ->indicateUsing(function (array $data): array {
                $indicators = [];
                foreach (['min' => 'Ціна від', 'max' => 'Ціна до'] as $field => $label) {
                    if (is_numeric($data[$field] ?? null) && (float) $data[$field] >= 0) {
                        $indicators[] = Indicator::make($label . ' ' . $data[$field] . ' ₴')->removeField($field);
                    }
                }
                return $indicators;
            }),
            ...array_values($featureFilters),
        ]);

        $table
            ->filtersLayout(FiltersLayout::Dropdown)
            ->deferFilters()
            ->filtersFormColumns(1)
            ->filtersFormWidth('xl')
            ->filtersFormSchema(fn (array $filters): array => [
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Group::make()->visible(fn (): bool => ! $this->isTableColumnToggledHidden('in_stock') || ! $this->isTableColumnToggledHidden('price'))->schema([
                        $filters['availability'],
                        Section::make('Ціна')->compact()->schema([$filters['price_range']]),
                    ]),
                    Section::make('Властивості товару')->compact()->columnSpan(1)
                        ->visible(fn (): bool => collect(array_keys($featureFilters))
                            ->contains(fn (string $flag): bool => ! $this->isTableColumnToggledHidden($flag)))
                        ->extraAttributes(['class' => 'catalog-filter-properties'])
                        ->schema(array_map(
                            fn (string $flag) => $filters[$flag],
                            array_values(array_filter(array_keys($featureFilters), fn (string $flag): bool => isset($filters[$flag])))
                        )),
                ]),
                Section::make('Додатково')->compact()->schema([$filters['category']]),
            ])
            ->filtersTriggerAction(fn (TableAction $action) => $action->button()->label('Фільтри')
                ->color($this->getTable()->getActiveFiltersCount() ? 'primary' : 'gray')
                ->extraAttributes(['class' => 'catalog-filters-trigger', 'x-on:catalog-filters-applied.window' => 'close()']))
            ->filtersApplyAction(fn (TableAction $action) => $action->label('Застосувати фільтри')
                ->color('primary'))
            ->toggleColumnsTriggerAction(fn (TableAction $action) => $action->button()->label('Колонки')->color('gray'))
            ->columnToggleFormMaxHeight('360px')
            ->columnToggleFormWidth('xs')
            ->searchPlaceholder(fn (): string => $this->category ? 'Пошук товарів у цій категорії...' : 'Пошук товарів...');

        $columns = [
            ViewColumn::make('main_image')
                ->label('Фото')
                ->toggleable()
                ->view('filament.tables.columns.catalog-product-photo'),
            ...array_values($table->getColumns()),
        ];

        $order = $this->getSavedCatalogColumnOrder();
        if ($order !== []) {
            $positions = array_flip($order);
            usort($columns, fn ($a, $b) => ($positions[$a->getName()] ?? PHP_INT_MAX) <=> ($positions[$b->getName()] ?? PHP_INT_MAX));
        }

        return $table->columns($columns);
    }

    public function reorderCatalogColumns(string $source, string $target): void
    {
        $names = array_map(fn ($column) => $column->getName(), $this->getTable()->getColumns());
        if (! in_array($source, $names, true) || ! in_array($target, $names, true) || $source === $target) {
            return;
        }

        $order = array_values(array_filter($this->getSavedCatalogColumnOrder(), fn ($name) => in_array($name, $names, true)));
        $order = array_values(array_unique([...$order, ...$names]));
        $from = array_search($source, $order, true);
        $to = array_search($target, $order, true);
        array_splice($order, $from, 1);
        if ($from < $to) {
            $to--;
        }
        array_splice($order, $to, 0, [$source]);

        $this->catalogColumnOrder = $order;
        session()->put($this->getCatalogColumnOrderSessionKey(), $order);
        $this->applyCatalogColumnOrder($order);
    }

    public function restoreCatalogColumnOrder(?string $storedOrder): void
    {
        $decoded = json_decode($storedOrder ?? '', true);
        if (! is_array($decoded)) {
            return;
        }

        $available = array_map(fn ($column) => $column->getName(), $this->getTable()->getColumns());
        $order = array_values(array_unique(array_filter($decoded, fn ($name) => is_string($name) && in_array($name, $available, true))));
        $order = array_values(array_unique([...$order, ...$available]));
        $this->catalogColumnOrder = $order;
        session()->put($this->getCatalogColumnOrderSessionKey(), $order);
        $this->applyCatalogColumnOrder($order);
    }

    protected function applyCatalogColumnOrder(array $order): void
    {
        $columns = array_values($this->getTable()->getColumns());
        $positions = array_flip($order);
        usort($columns, fn ($a, $b) => ($positions[$a->getName()] ?? PHP_INT_MAX) <=> ($positions[$b->getName()] ?? PHP_INT_MAX));
        $this->getTable()->columns($columns);
    }

    public function restoreCatalogColumnVisibility(?string $storedState): void
    {
        $decoded = json_decode($storedState ?? '', true);
        if (! is_array($decoded)) {
            return;
        }

        $state = [];
        foreach ($this->getTable()->getColumns() as $column) {
            $name = $column->getName();
            if ($column->isToggleable() && array_key_exists($name, $decoded)) {
                $state[$name] = (bool) $decoded[$name];
            }
        }

        $this->toggledTableColumns = array_replace($this->getDefaultTableColumnToggleState(), $state);
        session()->put($this->getTableColumnToggleFormStateSessionKey(), $this->toggledTableColumns);
        $this->getTableColumnToggleForm()->fill($this->toggledTableColumns);
    }

    public function updatedToggledTableColumns(): void
    {
        parent::updatedToggledTableColumns();
        $this->dispatch('catalog-columns-visibility-saved', state: $this->toggledTableColumns);
    }

    protected function getSavedCatalogColumnOrder(): array
    {
        $saved = session()->get($this->getCatalogColumnOrderSessionKey(), []);
        return is_array($saved) ? $saved : [];
    }

    protected function getCatalogColumnOrderSessionKey(): string
    {
        return 'catalog.columns.order.' . Filament::auth()->id();
    }

    protected function getTableColumnToggleFormSchema(): array
    {
        $schema = array_values(array_filter(
            parent::getTableColumnToggleFormSchema(),
            fn ($field): bool => $field->getName() !== 'title_search',
        ));
        if ($this->catalogView === 'compact') {
            foreach ($schema as $field) {
                if ($field->getName() === 'title') {
                    $field->disabled()->dehydrated();
                }
            }
        }
        return $schema;
    }

    public function getTableColumnToggleFormStateSessionKey(): string
    {
        return parent::getTableColumnToggleFormStateSessionKey() . '.' . Filament::auth()->id()
            . ($this->catalogView === 'compact' ? '.compact' : '');
    }

    protected function getDefaultTableColumnToggleState(): array
    {
        $state = parent::getDefaultTableColumnToggleState();
        if ($this->catalogView === 'compact') {
            foreach ($state as $name => &$visible) {
                $visible = in_array($name, ['title', 'sku', 'price', 'old_price', 'discount_percent', 'in_stock', 'is_new', 'is_hit', 'is_home', 'is_dont_forget', 'is_recommended', 'sort', 'quantity'], true);
            }
        }
        return $state;
    }

    public function isTableColumnToggledHidden(string $name): bool
    {
        if ($this->catalogView === 'compact' && in_array($name, ['main_image', 'title'], true)) {
            return $name === 'main_image';
        }
        return parent::isTableColumnToggledHidden($name);
    }

    public function expandPageVariants(): void
    {
        $this->expandedProducts = collect($this->getTableRecords()->all())->filter(fn ($product) => $product->variants_count > 0)->pluck('id')->all();
    }

    public function collapsePageVariants(): void
    {
        $this->expandedProducts = [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('price_history')
                ->label('Журнал зміни цін')
                ->icon('heroicon-o-clock')
                ->url(fn (): string => ProductPriceChangeBatchResource::getUrl('index')),
            Actions\Action::make('catalog_mass_price_change')
                ->label('Масова зміна цін')
                ->icon('heroicon-o-banknotes')
                ->visible(fn (): bool => $this->getTable()->getAction('mass_price_change')?->isVisible() ?? false)
                ->action(fn () => $this->mountTableAction('mass_price_change')),
            Actions\Action::make('catalog_import_products_excel')
                ->label(__('product.actions.import_products_excel'))
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => $this->getTable()->getAction('import_products_excel')?->isVisible() ?? false)
                ->action(fn () => $this->mountTableAction('import_products_excel')),
            Actions\CreateAction::make('create_product')
                ->label('Додати товар')
                ->url(fn (): string => ProductResource::getUrl('create', array_filter([
                    'category' => $this->category,
                    'source' => 'catalog',
                    'return_category' => $this->category,
                ], fn ($value) => $value !== null))),
        ];
    }

    public function selectCategory(?int $categoryId): void
    {
        $this->category = $categoryId > 0 ? $categoryId : null;
        $this->syncCategoryFilter();
        $this->resetPage();
        $this->expandedProducts = [];
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        // Category ordering reselects bs_products.*, so add the count afterwards.
        // Otherwise the expansion arrow disappears when a category is selected.
        return parent::applySortingToTableQuery($query)->withCount('variants');
    }

    public function toggleProductVariants(int $productId): void
    {
        if (! $this->getFilteredTableQuery()->whereKey($productId)->exists()) {
            return;
        }
        if (in_array($productId, $this->expandedProducts, true)) {
            $this->expandedProducts = array_values(array_diff($this->expandedProducts, [$productId]));
        } else {
            $this->expandedProducts[] = $productId;
        }
    }

    public function visibleProductVariants($records): Collection
    {
        $parentIds = collect($records->all())->pluck('id')->intersect($this->expandedProducts);
        if ($this->catalogView === 'compact' && filled($this->tableSearch)) {
            $search = '%' . trim((string) $this->tableSearch) . '%';
            $parentIds = $parentIds->merge(collect($records->all())->filter(
                fn (Product $product): bool => $product->variants()->where(fn (Builder $query) => $query
                    ->where('sku', 'like', $search)->orWhere('short_name', 'like', $search)->orWhere('title', 'like', $search))->exists()
            )->pluck('id'))->unique();
        }
        if ($parentIds->isEmpty()) {
            return collect();
        }
        return Product::query()->whereIn('parent_id', $parentIds)->with(['mainCategory', 'unit'])
            ->orderByRaw('CASE WHEN variant_display_sort IS NULL THEN 1 ELSE 0 END')
            ->orderBy('variant_display_sort')->orderBy('sort')->orderBy('id')->get()->groupBy('parent_id');
    }

    public function compactSearchMatches(Product $record): bool
    {
        $search = mb_strtolower(trim((string) $this->tableSearch));
        return $search !== '' && (
            str_contains(mb_strtolower((string) $record->sku), $search)
            || str_contains(mb_strtolower((string) $record->short_name), $search)
            || str_contains(mb_strtolower(implode(' ', $record->getTranslations('title'))), $search)
        );
    }

    protected function resolveTableRecord(?string $key): ?Model
    {
        $record = parent::resolveTableRecord($key);
        if ($record || ! $key) {
            return $record;
        }
        $variant = Product::query()->find($key);
        if ($variant?->parent_id && in_array($variant->parent_id, $this->expandedProducts, true)
            && parent::resolveTableRecord((string) $variant->parent_id)) {
            return $variant;
        }
        return null;
    }

    public function applyTableFilters(): void
    {
        $this->getTableFiltersForm()->getState();
        parent::applyTableFilters();
        $categoryId = (int) data_get($this->tableFilters, 'category.value', 0);
        $this->category = $categoryId > 0 ? $categoryId : null;
        $this->dispatch('catalog-filters-applied');
    }

    public function moveCategory(int $categoryId, ?int $targetId, bool $asChild = false): void
    {
        abort_unless(auth('admin')->user()?->can('reorder_product::category'), 403);

        $category = ProductCategory::findOrFail($categoryId);
        $target = $targetId ? ProductCategory::find($targetId) : null;

        if ($category->is($target) || ($target && in_array($target->id, $category->getDescendantIds(), true))) {
            return;
        }

        $newParentId = $asChild ? $target?->id : ($target?->parent_id ?? -1);
        $category->update(['parent_id' => $newParentId]);

        if ($target) {
            ProductCategory::query()
                ->where('parent_id', $newParentId)
                ->whereKeyNot($category->id)
                ->orderBy('order')
                ->get()
                ->each(function (ProductCategory $item, int $index) use ($target, $category): void {
                    $order = $item->id === $target->id ? $index + ($category->id < $target->id ? 0 : 1) : $index;
                    $item->updateQuietly(['order' => $order]);
                });
        }

        $this->catalogCategories = null;
        $this->dispatch('$refresh');
    }

    public function categoryTree(): Collection
    {
        $categories = $this->siteCategories();

        $grouped = $categories->groupBy(fn (ProductCategory $category) => (int) ($category->parent_id ?? 0));
        $search = mb_strtolower(trim($this->categorySearch));
        $build = function (int $parentId) use (&$build, $grouped, $search): Collection {
            return ($grouped->get($parentId, collect()))->map(function (ProductCategory $category) use (&$build): ProductCategory {
                $category->setRelation('children', $build($category->id));
                return $category;
            })->filter(function (ProductCategory $category) use ($search): bool {
                return $search === ''
                    || str_contains(mb_strtolower($category->name), $search)
                    || $category->children->isNotEmpty();
            })->values();
        };

        return $build((int) ProductCategory::defaultParentKey());
    }

    public function selectedCategoryRecord(): ?ProductCategory
    {
        return $this->category ? $this->siteCategories()->get($this->category) : null;
    }

    protected function categoryBranchIds(int $categoryId): array
    {
        $categories = $this->siteCategories();
        $ids = [];
        $pending = [$categoryId];
        $children = $categories->groupBy('parent_id');
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($ids[$id]) || ! $categories->has($id)) {
                continue;
            }
            $ids[$id] = $id;
            foreach ($children->get($id, collect()) as $child) {
                $pending[] = $child->id;
            }
        }
        return array_values($ids);
    }

    protected function siteCategories(): Collection
    {
        if ($this->catalogCategories !== null) {
            return $this->catalogCategories;
        }
        $categories = $this->siteCategoryQuery()->orderBy('order')->orderBy('id')->get()->keyBy('id');
        $base = fn (): Builder => Product::query()->parents()
            ->where(fn (Builder $query) => $query->whereNull('is_imported')->orWhere('is_imported', false));
        $memberships = $base()->select('bs_products.id as product_id', 'bs_products.category_id')
            ->toBase()->union(
                $base()->join('bs_product_product_category as membership', 'membership.product_id', '=', 'bs_products.id')
                    ->select('bs_products.id as product_id', 'membership.product_category_id as category_id')->toBase()
            )->get();
        $productsByCategory = [];
        foreach ($memberships as $membership) {
            $id = (int) $membership->category_id;
            $visited = [];
            while ($categories->has($id) && ! isset($visited[$id])) {
                $visited[$id] = true;
                $productsByCategory[$id][$membership->product_id] = true;
                $id = (int) $categories->get($id)->parent_id;
            }
        }
        foreach ($categories as $category) {
            $category->setAttribute('products_count', count($productsByCategory[$category->id] ?? []));
        }
        return $this->catalogCategories = $categories;
    }

    public function categoryBreadcrumbs(?ProductCategory $selected): Collection
    {
        if (! $selected) {
            return collect();
        }

        $categories = $this->siteCategories();
        $path = collect();
        $visited = [];
        $category = $selected;

        while ($category && ! isset($visited[$category->id])) {
            $visited[$category->id] = true;
            $path->prepend($category);
            $category = $categories->get($category->parent_id);
        }

        return $path;
    }

    protected function siteCategoryQuery(): Builder
    {
        return ProductCategory::query()
            ->whereNotNull('slug')
            ->where('slug', 'not like', 'src-%');
    }

    protected function syncCategoryFilter(): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['category'] = ['value' => $this->category];
        $this->tableDeferredFilters = $this->tableFilters;
    }
}
