<?php

namespace App\Support\Traits;

use App\Models\Shop\Client;
use App\Models\Shop\Product;
use App\Models\Shop\Characteristic;
use App\Services\CatalogCacheService;
use App\Support\GuestFavoritesStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

trait HasCatalogFilters
{
    protected function getFilterCharacteristics(array $menuSlugs = []): Collection
    {
        $locale = app()->getLocale();
        $cacheKey = app(CatalogCacheService::class)->key('filter_characteristics', $menuSlugs, $locale);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($menuSlugs) {
            $rootIds = $this->catalogRootIds($menuSlugs);

            if ($rootIds->isEmpty()) {
                return collect();
            }

            $childIds = Product::query()
                ->active()
                ->whereIn('parent_id', $rootIds)
                ->where(function (Builder $w) {
                    $this->applyMainSiteProductFilter($w);
                })
                ->pluck('id');

            $productIds = $rootIds->merge($childIds)->unique();

            $valueCounts = DB::table('bs_product_characteristic_value')
                ->whereIn('product_id', $productIds)
                ->groupBy('characteristic_value_id')
                ->select('characteristic_value_id', DB::raw('COUNT(DISTINCT product_id) as cnt'))
                ->pluck('cnt', 'characteristic_value_id');

            $slugs = ['miaso', 'moreprodukti', 'sir', 'sousi', 'ovoci'];

            $characteristics = Characteristic::query()
                ->whereIn('slug', $slugs)
                ->with('values')
                ->get()
                ->keyBy('slug');

            foreach ($characteristics as $char) {
                $char->setRelation(
                    'values',
                    $char->values->filter(function ($val) use ($valueCounts) {
                        $cnt = (int) ($valueCounts[$val->id] ?? 0);
                        $val->products_count = $cnt;
                        return $cnt > 0;
                    })->values()
                );
            }

            return $characteristics;
        });
    }

    protected function getPriceBounds(string $slug = 'all', array $menuSlugs = []): array
    {
        $effectiveSlugs = $menuSlugs ?: ($slug !== 'all' ? [$slug] : []);
        $cacheKey = app(CatalogCacheService::class)->key('price_bounds', $effectiveSlugs);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($effectiveSlugs) {
            $rootIds = $this->catalogRootIds($effectiveSlugs);

            if ($rootIds->isEmpty()) {
                return [0, 0];
            }

            $row = Product::query()
                ->where(function (Builder $q) use ($rootIds) {
                    $q->whereIn('id', $rootIds)
                        ->orWhereIn('parent_id', $rootIds);
                })
                ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                ->first();

            $min = (int) floor($row->min_price ?? 0);
            $max = (int) ceil($row->max_price ?? 0);

            if ($min === 0 && $max === 0) {
                return [0, 0];
            }

            if ($min === $max) {
                $min = max(0, $min - 10);
                $max = $max + 10;
            }

            return [$min, $max];
        });
    }

    protected function catalogRootIds(array $menuSlugs = []): Collection
    {
        $cacheKey = app(CatalogCacheService::class)->key('root_ids', $menuSlugs);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($menuSlugs) {
            return Product::query()
                ->active()
                ->MainProduct()
                ->where(function (Builder $w) {
                    $this->applyMainSiteProductFilter($w);
                })
                ->when(! empty($menuSlugs), function (Builder $query) use ($menuSlugs): void {
                    $query->where(function (Builder $categoryQuery) use ($menuSlugs): void {
                        $categoryQuery
                            ->whereHas('categories', function (Builder $relatedQuery) use ($menuSlugs): void {
                                $relatedQuery->whereIn('slug', $menuSlugs);
                            })
                            ->orWhereHas('mainCategory', function (Builder $relatedQuery) use ($menuSlugs): void {
                                $relatedQuery->whereIn('slug', $menuSlugs);
                            });
                    });
                })
                ->pluck('id');
        });
    }

    protected function applyFilters(Builder $q, Request $request): void
    {
        $q->where(function (Builder $w) {
            $this->applyMainSiteProductFilter($w);
        });

        // ----- меню -----
        $menuSlugs = collect($request->input('menu', []))
            ->filter()
            ->unique()
            ->values();

        if ($menuSlugs->isNotEmpty()) {
            $q->where(function (Builder $qq) use ($menuSlugs) {
                $qq->whereHas('categories', function (Builder $qqq) use ($menuSlugs) {
                    $qqq->whereIn('slug', $menuSlugs);
                })
                    ->orWhereHas('mainCategory', function (Builder $qqq) use ($menuSlugs) {
                        $qqq->whereIn('slug', $menuSlugs);
                    });
            });
        }

        // ----- цена -----
        $priceMin = $request->input('price_min');
        $priceMax = $request->input('price_max');

        if ($priceMin !== null && $priceMin !== '') {
            $q->where('price', '>=', (float) $priceMin);
        }

        if ($priceMax !== null && $priceMax !== '') {
            $q->where('price', '<=', (float) $priceMax);
        }

        // ----- характеристики -----
        $charsInput = $request->input('chars', []);
        $selectedValueIds = collect($charsInput)
            ->flatten()
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($selectedValueIds->isNotEmpty()) {
            $productIds = DB::table('bs_product_characteristic_value')
                ->whereIn('characteristic_value_id', $selectedValueIds)
                ->pluck('product_id')
                ->unique();

            if ($productIds->isEmpty()) {
                $q->whereRaw('1 = 0');
            } else {
                $rootIds = Product::query()
                    ->whereIn('id', $productIds)
                    ->selectRaw('CASE WHEN parent_id IS NULL THEN id ELSE parent_id END AS root_id')
                    ->pluck('root_id')
                    ->unique();

                if ($rootIds->isEmpty()) {
                    $q->whereRaw('1 = 0');
                } else {
                    $q->whereIn('id', $rootIds);
                }
            }
        }
    }

    protected function favoriteIds(): array
    {
        if ($client = $this->currentClient()) {
            return DB::table('bs_favorites')
                ->where('client_id', $client->id)
                ->pluck('product_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        return GuestFavoritesStore::idsFromRequest();
    }

    private function currentClient(): ?Client
    {
        $u = auth('client')->user();
        if ($u instanceof Client) return $u;

        $u = auth()->user();
        return $u instanceof Client ? $u : null;
    }

    /**
     * Товары только основного сайта (без импортированных).
     */
    private function applyMainSiteProductFilter(Builder $query): void
    {
        $query->whereNull('is_imported')
            ->orWhere('is_imported', false);
    }

    /**
     * Применить сортировку к запросу товаров по параметру ?sort=
     */
    protected function applySort(Builder $query, Request $request): Builder
    {
        $sort = $request->query('sort', 'popular');

        switch ($sort) {
            case 'price_asc':
                // сортировка по цене ↑
                $query->orderBy('price', 'asc');
                break;

            case 'price_desc':
                // сортировка по цене ↓
                $query->orderBy('price', 'desc');
                break;

            case 'discount_asc':
                // сначала товары со скидкой, затем по проценту скидки ↑
                $query->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN 0 ELSE 1 END ASC")
                    ->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN ((old_price - price) / old_price) * 100 ELSE 0 END ASC");
                break;

            case 'discount_desc':
                // сначала товары со скидкой, затем по проценту скидки ↓
                $query->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN 0 ELSE 1 END ASC")
                    ->orderByRaw("CASE WHEN old_price IS NOT NULL AND old_price > 0 AND old_price > price THEN ((old_price - price) / old_price) * 100 ELSE 0 END DESC");
                break;

            case 'new':
                // Сначала товары с флагом "новинка", затем по дате, затем стабильный порядок
                $query->orderByDesc('is_new')
                    ->orderBy('created_at', 'desc')
                    ->orderBy('sort', 'asc');
                break;

            case 'popular':
            default:
                // Базовый порядок каталога соответствует сортировке в админке.
                $query->orderBy('sort', 'asc')
                    ->orderBy('id', 'asc');
                break;
        }

        return $query;
    }

    /**
     * Сортировка уже «готовых» карточек (массивов) после презентера.
     */
    protected function sortCardCollection($items, Request $request)
    {
        // если пришёл массив — оборачиваем в коллекцию
        if (! $items instanceof Collection) {
            $items = collect($items);
        }

        $sort = $request->query('sort', 'popular');

        $items = match ($sort) {
            // Цена ↑
            'price_asc' => $items->sortBy(function ($p) {
                return $p['price'] ?? $p['final_price'] ?? $p['min_price'] ?? 0;
            }),

            // Цена ↓
            'price_desc' => $items->sortByDesc(function ($p) {
                return $p['price'] ?? $p['final_price'] ?? $p['min_price'] ?? 0;
            }),

            // Знижка ↑
            'discount_asc' => $items->sortBy(fn ($p) => [
                $this->resolveDiscountPercentForCard($p) > 0 ? 0 : 1,
                $this->resolveDiscountPercentForCard($p),
            ]),

            // Знижка ↓
            'discount_desc' => $items->sortBy(fn ($p) => [
                $this->resolveDiscountPercentForCard($p) > 0 ? 0 : 1,
                -1 * $this->resolveDiscountPercentForCard($p),
            ]),

            // Новинки/Популярні: сохраняем SQL-порядок
            'new' => $items,

            // Популярні — базовый порядок (по sort)
            default => $items,
        };

        return $items->values(); // нормализуем ключи 0..N
    }

    protected function resolveDiscountPercentForCard(array $product): float
    {
        $price = (float) ($product['price'] ?? $product['final_price'] ?? $product['min_price'] ?? 0);
        $oldPrice = (float) ($product['old_price'] ?? $product['price_old'] ?? $price);

        if ($oldPrice <= 0 || $oldPrice <= $price || $price <= 0) {
            return 0.0;
        }

        return (($oldPrice - $price) / $oldPrice) * 100;
    }
}
