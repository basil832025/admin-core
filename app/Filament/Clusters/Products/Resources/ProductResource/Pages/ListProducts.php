<?php

namespace App\Filament\Clusters\Products\Resources\ProductResource\Pages;

// use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
// use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use App\Filament\Clusters\Products\Resources\ProductResource;
use App\Models\Language;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListProducts extends ListRecords
{
    //   use Translatable;
    protected static string $resource = ProductResource::class;


    public function reorderTable(array $order): void
    {
        $categoryId = $this->getSelectedCategoryFilterId();

        if ($categoryId <= 0) {
            parent::reorderTable($order);

            return;
        }

        DB::transaction(function () use ($categoryId, $order): void {
            foreach (array_values($order) as $index => $productId) {
                DB::table('bs_product_product_category')->updateOrInsert(
                    [
                        'product_id' => (int) $productId,
                        'product_category_id' => $categoryId,
                    ],
                    [
                        'sort_order' => $index + 1,
                    ],
                );
            }
        });
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        $categoryId = $this->getSelectedCategoryFilterId();

        if ($categoryId <= 0 || (! $this->isTableReordering() && filled($this->tableSortColumn))) {
            return parent::applySortingToTableQuery($query);
        }

        return $query
            ->leftJoin('bs_product_product_category as category_sort', function ($join) use ($categoryId): void {
                $join->on('category_sort.product_id', '=', 'bs_products.id')
                    ->where('category_sort.product_category_id', '=', $categoryId);
            })
            ->select('bs_products.*')
            ->orderByRaw('CASE WHEN category_sort.sort_order IS NULL THEN 1 ELSE 0 END asc')
            ->orderBy('category_sort.sort_order')
            ->orderBy('bs_products.sort')
            ->orderBy('bs_products.id');
    }

    protected function getSelectedCategoryFilterId(): int
    {
        return (int) data_get($this->tableFilters, 'category.value', 0);
    }

    protected function getHeaderActions(): array
    {
        // Кэшируем список языков на 1 час для оптимизации
        $languages = cache()->remember('active_languages_map', 3600, function () {
            return Language::where('active', true)
                ->pluck('name', 'code')
                ->mapWithKeys(fn ($name, $code) => [strtolower($code) => $name])
                ->toArray();
        });

        $items = collect(static::getResource()::getActiveLocales()) // ['uk','en','ru', ...]
            ->map(function (string $code) use ($languages) {
                $label = $languages[$code] ?? strtoupper($code);

                return Actions\Action::make("locale-$code")
                    ->label($label)
                    ->icon('heroicon-m-language')
                    ->action(function () use ($code) {
                        session(['locale' => $code]);
                        app()->setLocale($code);
                        Carbon::setLocale($code);
                        $this->dispatch('$refresh'); // Livewire v3
                    });
            })
            ->all();

        return [
            Actions\ActionGroup::make($items)
                ->label(strtoupper(app()->getLocale()))
                ->icon('heroicon-m-language'),
            Actions\CreateAction::make(),
        ];
    }
}
