<?php

namespace App\Http\Controllers\Front;
use App\Support\Traits\HasCatalogFilters;
use App\Models\Blog;
use App\Models\Banner;
use App\Models\Pages;
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
        $page = Pages::query()->where('slug', 'home')->first();

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

        // Helper: apply common main-page filters.
        $applyMainPageBase = function ($q): void {
            $q->where(function ($w) {
                $w->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            });
        };

        $excludeRootIds = [];

        // 0) АКЦИИ (is_promo + is_home) — должны быть первыми
        $promoQuery = Product::withCardRelations()
            ->active()->home()->where('is_promo', 1)->cardSelect()->MainProduct()->Pie();
        $applyMainPageBase($promoQuery);
        $this->applySort($promoQuery, request());
        $promo_products = $promoQuery->get();
        $promo = (new ProductCardPresenter($locale))->collection($promo_products);
        $excludeRootIds = array_values(array_unique(array_merge($excludeRootIds, $promo_products->pluck('id')->all())));

        // 1) ХІТИ (is_hit + is_home)
        $hitsQuery = Product::withCardRelations()
            ->active()->home()->hit()->cardSelect()->MainProduct()->Pie()
            ->when(!empty($excludeRootIds), fn ($q) => $q->whereNotIn('id', $excludeRootIds));
        $applyMainPageBase($hitsQuery);
        $this->applySort($hitsQuery, request());
        $hits_products = $hitsQuery->get();
        $hits = (new ProductCardPresenter($locale))->collection($hits_products);
        $excludeRootIds = array_values(array_unique(array_merge($excludeRootIds, $hits_products->pluck('id')->all())));

        // 2) НОВИНКИ (is_new + is_home)
        $newsQuery = Product::withCardRelations()
            ->active()->home()->new()->cardSelect()->MainProduct()->Pie()
            ->when(!empty($excludeRootIds), fn ($q) => $q->whereNotIn('id', $excludeRootIds));
        $applyMainPageBase($newsQuery);
        $this->applySort($newsQuery, request());
        $news_products = $newsQuery->get();
        $news = (new ProductCardPresenter($locale))->collection($news_products);
        $excludeRootIds = array_values(array_unique(array_merge($excludeRootIds, $news_products->pluck('id')->all())));

        $parentSlug = 'pies';

        $pickCategoryTitle = function (ProductCategory $cat) use ($locale): string {
            $title = method_exists($cat, 'getTranslation')
                ? ($cat->getTranslation('title', $locale) ?? $cat->title)
                : $cat->title;
            return is_string($title) ? (string) $title : '';
        };

        $buildHomeCategorySection = function (string $slug, string $title) use ($locale, $applyMainPageBase, &$excludeRootIds): ?array {
            $q = Product::withCardRelations()
                ->active()->home()->cardSelect()->MainProduct()
                ->where(function ($query) use ($slug) {
                    $query->whereHas('categories', fn($qq) => $qq->where('slug', $slug))
                        ->orWhereHas('mainCategory', fn($qq) => $qq->where('slug', $slug));
                })
                ->when(!empty($excludeRootIds), fn ($qq) => $qq->whereNotIn('id', $excludeRootIds));

            $applyMainPageBase($q);
            $this->applySort($q, request());

            $models = $q->get();
            if ($models->isEmpty()) {
                return null;
            }

            $excludeRootIds = array_values(array_unique(array_merge($excludeRootIds, $models->pluck('id')->all())));

            return [
                'title' => $title,
                'items' => (new ProductCardPresenter($locale))->collection($models),
                'slug'  => $slug,
            ];
        };

        $categorySections = [];

        // 3) Пироги по группам (только is_home)
        $pieChildren = Cache::remember("home:pies:children:$locale", 3600, function () use ($parentSlug) {
            $parent = ProductCategory::query()->where('slug', $parentSlug)->first();
            if (!$parent) {
                return collect();
            }
            return $parent->children()
                ->where('is_visible', 1)
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        });

        foreach ($pieChildren as $cat) {
            /** @var ProductCategory $cat */
            $slug = (string) $cat->slug;
            if ($slug === '') {
                continue;
            }
            $section = $buildHomeCategorySection($slug, $pickCategoryTitle($cat));
            if ($section) {
                $categorySections[] = $section;
            }
        }

        // 4) Остальные группы по группам (только is_home)
        $otherRoots = Cache::remember("home:other:roots:v2:$locale", 3600, function () use ($parentSlug) {
            return ProductCategory::query()
                ->where(function ($q) {
                    $q->whereNull('parent_id')->orWhere('parent_id', -1);
                })
                ->where('is_visible', 1)
                ->where('slug', '!=', $parentSlug)
                ->orderBy('order')
                ->orderBy('id')
                ->get();
        });

        foreach ($otherRoots as $root) {
            /** @var ProductCategory $root */
            $children = $root->children()
                ->where('is_visible', 1)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            $groups = $children->isNotEmpty() ? $children : collect([$root]);

            foreach ($groups as $cat) {
                /** @var ProductCategory $cat */
                $slug = (string) $cat->slug;
                if ($slug === '') {
                    continue;
                }
                $section = $buildHomeCategorySection($slug, $pickCategoryTitle($cat));
                if ($section) {
                    $categorySections[] = $section;
                }
            }
        }

        $homeBlog = Blog::query()
            ->published()
            ->where('slug', 'home_blog')
            ->first();

        return view('home', [
            'banners'          => $banners,
            'promo'            => $promo,
            'hits'             => $hits,
            'news'             => $news,
            'favoriteIds' => $favoriteIds,
            'priceMin'    => $priceMin,
            'priceMax'    => $priceMax,
            'filterCharacteristicGroups' => $filterCharacteristicGroups,
            'categorySections' => $categorySections,
            'homeBlog' => $homeBlog,
            'page' => $page,
        ]);
    }
}
