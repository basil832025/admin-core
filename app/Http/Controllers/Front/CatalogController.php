<?php

namespace App\Http\Controllers\Front;
use App\Support\Traits\HasCatalogFilters;
use App\Http\Controllers\Controller;
use App\Models\Shop\Client;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Support\Presenters\ProductCardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\Shop\Characteristic;


class CatalogController extends Controller
{
    use HasCatalogFilters;
    public function show(string $slug)
    {
        $locale = app()->getLocale();

        $ids    = $this->favoriteIds();
        // 0) Виртуальные слаги: без категории, просто подбираем товары по скоупам
        if ($slug === 'pies_hits' || $slug === 'pies_news') {
            // Заголовок секции (можно заменить на st_value(...) если уже подключили словарь)
            $title = $slug === 'pies_hits'
                ? (function_exists('st') ? st('menu.hits','Хіти') : 'Хіти')
                : (function_exists('st') ? st('menu.news','Новинки') : 'Новинки');

            $q = Product::withCardRelations()
                ->active()->cardSelect()->MainProduct()
                ->where(function (Builder $w) {
                    $w->whereNull('is_imported')
                        ->orWhere('is_imported', false);
                });
            $this->applyFilters($q, request());
            $this->applySort($q, request());


            // ⬇️ Скоупы под виртуальные слаги
            if ($slug === 'pies_hits') {
                $q->hit();   // scopeHit()
            } else {
                $q->new();   // scopeNew() — если у тебя называется иначе (news / isNew), замени тут
            }

            $items = (new ProductCardPresenter($locale))->collection($q->get());
            $items = $this->sortCardCollection($items, request());
            $categorySections = [[
                'title' => $title,
                'items' => $items,
                'slug'  => $slug,
            ]];

            // границы цен для виртуального слага (по хитам/новинкам)
            [$priceMin, $priceMax] = $this->getPriceBounds($slug);
            $filterCharacteristicGroups = $this->getFilterCharacteristics();
            return view('pages.catalog.category', [
                'categorySections' => $categorySections,
                'favoriteIds'      => $ids,       // чтобы вьюхе было одинаково
                'priceMin'         => $priceMin,
                'priceMax'         => $priceMax,
                'filterCharacteristicGroups'=> $filterCharacteristicGroups,
            ]);
        }

        // 1) Обычный путь: реальная категория по slug

        $parent = ProductCategory::query()
            ->where('slug', $slug)
            ->first();

        $children = collect();

        if ($parent) {
            $children = $parent->children()
                ->orderBy('order')   // если поля нет — можно убрать
                ->orderBy('id')
                ->get();
        }

// 2) Собираем ассоциативный массив slug => title (локализовано)
        $homeCategorySlugs = $children->mapWithKeys(function (ProductCategory $cat) use ($locale) {
            // если используешь spatie/translatable
            $title = method_exists($cat, 'getTranslation')
                ? ($cat->getTranslation('title', $locale) ?? $cat->title)
                : $cat->title;

            return [$cat->slug => $title];
        })->all();

        if (empty($homeCategorySlugs)){
            $homeCategorySlugs = (function (?ProductCategory $cat) use ($locale) {
                if (!$cat) return [];

                $title = method_exists($cat, 'getTranslation')
                    ? ($cat->getTranslation('title', $locale) ?? $cat->title)
                    : $cat->title;

                return [$cat->slug => $title];
            })($parent);
        }

      /*  $category = ProductCategory::query()
            ->where('slug', $slug)
            ->firstOrFail();*/

        $categorySections = [];
        foreach ($homeCategorySlugs as $slug => $title) {
          /*  $items = Product::withCardRelations()
                ->active()->cardSelect()->MainProduct()->orderBy('sort')
                ->where(function ($q) use ($slug) {
                    $q->whereHas('categories', fn($qq) => $qq->where('slug', $slug))
                        ->orWhereHas('mainCategory', fn($qq) => $qq->where('slug', $slug));
                })
                ->get()  ;*/
            $q = Product::withCardRelations()
                ->active()->cardSelect()->MainProduct()
                ->where(function (Builder $w) {
                    $w->whereNull('is_imported')
                        ->orWhere('is_imported', false);
                });

            $hasFilters = request()->has('menu') ||
                request()->has('chars') ||
                request()->filled('price_min') ||
                request()->filled('price_max');

            if ($hasFilters) {
                // глобальный фильтр
                $this->applyFilters($q, request());
            } else {
                // старое поведение: только текущая категория
                $q->where(function ($qq) use ($slug) {
                    $qq->whereHas('categories', fn($qqq) => $qqq->where('slug', $slug))
                        ->orWhereHas('mainCategory', fn($qqq) => $qqq->where('slug', $slug));
                });
            }

            $this->applySort($q, request());

            $items = $q->get();

            // Добавляем ссылки (категория + продукт)

            // Презентер (если он форматирует карточки)
            $items = (new ProductCardPresenter($locale))->collection($items);
            $items = $this->sortCardCollection($items, request());
          //  dd($items);

            //->map($pack)->all();

            $favoriteIds = $ids; // <<< ВАЖНО
            $categorySections[] = [
                'title' => $title,
                'items' => $items,
                'slug'  => $slug,

                //  'link'  => route('catalog.category', ['slug' => $slug]),
            ];
        }
        // Границы цен для обычной категории
        [$priceMin, $priceMax] = $this->getPriceBounds($slug);
        $filterCharacteristicGroups = $this->getFilterCharacteristics();
      //  dd($filterCharacteristicGroups);
        return view('pages.catalog.category', [
            'categorySections' => $categorySections,
            'favoriteIds'      => $favoriteIds,
            'priceMin'         => $priceMin,
            'priceMax'         => $priceMax,
            'filterCharacteristicGroups'=> $filterCharacteristicGroups,
        ]);
    }

    public function filter(Request $request)
    {
        $locale = app()->getLocale();
        $favoriteIds = $this->favoriteIds();      // 👈 добавили

        $q = Product::withCardRelations()
            ->with(['mainCategory', 'categories'])
            ->active()
            ->cardSelect()
            ->MainProduct()
            ->where(function (Builder $w) {
                $w->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            });

        $this->applyFilters($q, $request);
        $this->applySort($q, $request);

        $items = $q->get();

        if ($items->isEmpty()) {
            // границы цен и характеристики тоже посчитаем
            [$priceMin, $priceMax] = $this->getPriceBounds('all');
            $filterCharacteristicGroups = $this->getFilterCharacteristics();

            return view('pages.filter', [
                'title'   => function_exists('st') ? st('filter.title', 'Результати фільтру') : __('Результати фільтру'),
                'groups'  => collect(),
                'filters' => $request->all(),
                'favoriteIds' => $favoriteIds,
                'priceMin'    => $priceMin,
                'priceMax'    => $priceMax,
                'filterCharacteristicGroups' => $filterCharacteristicGroups,
            ]);
        }

        // группируем по категории
        $grouped = $items->groupBy(function (Product $product) {
            if ($product->mainCategory) {
                return $product->mainCategory->id;
            }
            if ($product->categories->isNotEmpty()) {
                return $product->categories->first()->id;
            }
            return 'no-category';
        });

        $groups = $grouped->map(function ($products, $key) use ($locale, $request) {
            /** @var \App\Models\Shop\Product $first */
            $first = $products->first();

            $category = $first->mainCategory
                ?? $first->categories->first();

            $title = $category?->title ?? __('Без категорії');

            // 🔥 ДЕЛАЕМ ТО ЖЕ, ЧТО И В show():
            // презентер + sortCardCollection
            $cards = (new ProductCardPresenter($locale))->collection($products);
            $cards = $this->sortCardCollection($cards, $request);

            return [
                'title' => $title,
                'slug'  => $category?->slug,
                'items' => $cards,
            ];
        });

        [$priceMin, $priceMax] = $this->getPriceBounds('all');
        $filterCharacteristicGroups = $this->getFilterCharacteristics();

        return view('pages.filter', [
            'title'   => function_exists('st') ? st('filter.title', 'Результати фільтру') : __('Результати фільтру'),
            'groups'  => $groups,
            'filters' => $request->all(),
            'favoriteIds' => $favoriteIds,
            'priceMin'    => $priceMin,
            'priceMax'    => $priceMax,
            'filterCharacteristicGroups' => $filterCharacteristicGroups,
        ]);
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
                // по размеру скидки ↑
                // ЗАМЕНИ поля old_price / price на свои, если называются иначе
                $query->orderByRaw('(IFNULL(old_price, price) - price) ASC');
                break;

            case 'discount_desc':
                // по размеру скидки ↓
                $query->orderByRaw('(IFNULL(old_price, price) - price) DESC');
                break;

            case 'new':
                // Сначала товары с флагом "новинка", затем по дате, затем стабильный порядок
                $query->orderByDesc('is_new')
                    ->orderBy('created_at', 'desc')
                    ->orderBy('sort', 'asc');
                break;

            case 'popular':
            default:
                // Сначала товары с флагом "популярний", затем базовый порядок
                $query->orderByDesc('is_hit')
                    ->orderBy('sort', 'asc');
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
            'discount_asc' => $items->sortBy(function ($p) {
                $price    = $p['price'] ?? 0;
                $oldPrice = $p['old_price'] ?? $p['price_old'] ?? $price;
                return max($oldPrice - $price, 0);
            }),

            // Знижка ↓
            'discount_desc' => $items->sortByDesc(function ($p) {
                $price    = $p['price'] ?? 0;
                $oldPrice = $p['old_price'] ?? $p['price_old'] ?? $price;
                return max($oldPrice - $price, 0);
            }),

            // Новинки/Популярні: сохраняем SQL-порядок
            'new' => $items,

            // Популярні — базовый порядок (по sort)
            default => $items,
        };

        return $items->values(); // нормализуем ключи 0..N
    }
}
