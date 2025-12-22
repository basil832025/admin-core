<?php

namespace App\Http\Controllers\Front;
use App\Support\Traits\HasCatalogFilters;
use App\Models\Banner;
use App\Models\Shop\Product;
use App\Http\Controllers\Controller;
use App\Models\Shop\ProductCategory;
use App\Support\Presenters\ProductCardPresenter;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    use HasCatalogFilters;
    public function index()
    {

        $favoriteIds = $this->favoriteIds();
        [$priceMin, $priceMax] = $this->getPriceBounds('all');
        $filterCharacteristicGroups = $this->getFilterCharacteristics();

        $locale = app()->getLocale(); // 'uk' у тебя по умолчанию
        // ===== БАННЕРЫ =====
        $now = now();

        $banners = Banner::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort')
            ->get();

        // 1) ХІТИ
        $hits_products  = Product::withCardRelations()
            ->active()->hit()->cardSelect()->MainProduct()->Pie()
            ->inRandomOrder()->limit(6)->get();
         $hits = (new ProductCardPresenter($locale))->collection($hits_products);
        // 2) НОВИНКИ
        $news_products = Product::withCardRelations()
            ->active()->new()->cardSelect()->MainProduct()->Pie()
             ->inRandomOrder()->limit(6)->get();
         $news = (new ProductCardPresenter($locale))->collection($news_products);

        $parentSlug = 'pies';

// 1) Тянем детей родителя pies (кешируем на час)
        $children = Cache::remember("home:pies:children:$locale", 3600, function () use ($parentSlug) {
            $parent = ProductCategory::query()->where('slug', $parentSlug)->first();

            if (!$parent) {
                return collect(); // чтоб код дальше не упал
            }

            // если есть признак активности/сортировки — добавь свои scope'ы
            return $parent->children()   // стандартная связь hasMany по parent_id
            ->orderBy('order')       // если поля нет — удали строку
            ->orderBy('id')          // стабильность сортировки
            ->get();
        });

// 2) Собираем ассоциативный массив slug => title (локализовано)
        $homeCategorySlugs = $children->mapWithKeys(function (ProductCategory $cat) use ($locale) {
            // если используешь spatie/translatable
            $title = method_exists($cat, 'getTranslation')
                ? ($cat->getTranslation('title', $locale) ?? $cat->title)
                : $cat->title;

            return [$cat->slug => $title];
        })->all();
        $categorySections = [];
        foreach ($homeCategorySlugs as $slug => $title) {
            $items = Product::withCardRelations()
                ->active()->home()->cardSelect()->MainProduct()
                 ->where(function ($q) use ($slug) {
                    $q->whereHas('categories', fn($qq) => $qq->where('slug', $slug))
                        ->orWhereHas('mainCategory', fn($qq) => $qq->where('slug', $slug));
                })
                ->inRandomOrder()->limit(6)->get();
                //->map($pack)->all();
            $items = (new ProductCardPresenter($locale))->collection($items);
            $categorySections[] = [
                'title' => $title,
                'items' => $items,
                'slug'  => $slug,
              //  'link'  => route('catalog.category', ['slug' => $slug]),
            ];
        }

        return view('home', [
            'banners'          => $banners,
            'hits'             => $hits,
            'news'             => $news,
            'favoriteIds' => $favoriteIds,
            'priceMin'    => $priceMin,
            'priceMax'    => $priceMax,
            'filterCharacteristicGroups' => $filterCharacteristicGroups,
            'categorySections' => $categorySections,
        ]);
    }
}
