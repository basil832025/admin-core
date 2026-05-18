<?php

namespace App\Providers;

use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Services\CatalogCacheService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Автоматически подсовываем данные в partials.menu
        View::composer([
            'partials.menu',
            'partials.footer',
            'product.filter-panel',      // наш шаблон фильтра
            'components.menu-drawer',      // наш шаблон фильтра
          ], function ($view) {
            $locale = app()->getLocale();
            $brand  = '#FF7500';
            $prefix = in_array($locale, ['ru', 'en'], true) ? '/' . $locale : '';
            $countsBySlug = $this->menuProductCounts();

            // корневые = parent_id = -1
            $roots = ProductCategory::query()
                ->where('is_visible', 1)
                ->where('parent_id', -1)
                ->orderBy('order')
                ->with([
                    'children' => fn ($q) => $q->where('is_visible', 1)
                        ->orderBy('order')
                        ->with([
                            'children' => fn ($q) => $q->where('is_visible', 1)
                                ->orderBy('order')
                                ->with('children'), // добавляй уровни, если нужно глубже
                        ]),
                ])
                ->get();
         //   dd($roots);
            $flat = collect();
// анонимная функция проходит по всему масвиву и собирает родителей и если есть дети то и деьтей в один уровень
            $walk = function ($node) use (&$walk, &$flat, $locale, $prefix, $countsBySlug) {
                // сам узел
                $flat->push([
                    'id'    => $node->id,
                    'label' => $node->getTranslation('title', $locale),
                    'slug'  => $node->slug,
                    'url'   => $prefix . '/' . ltrim((string) $node->slug, '/'),
                    'order' => (int) ($node->order ?? 0),
                    'count' => (int) ($countsBySlug[$node->slug] ?? 0),
                ]);
                // если родителький слаг Все пироги то выведим Хиты и новинки
                if ($node->slug=='pies')
                {
                    $flat->push([
                        'id'    => 5000,
                        'label' => st('menu.hits','Хіти'),
                        'slug'  => 'pies_hits',
                        'url'   => $prefix . '/pies_hits',
                        'order' => (int) ($node->order ?? 0),
                    ]);
                    $flat->push([
                        'id'    => 5001,
                        'label' => st('menu.news','Новинки'),
                        'slug'  => 'pies_news',
                        'url'   => $prefix . '/pies_news',
                        'order' => (int) ($node->order ?? 0),
                    ]);
                }
                // сразу за родителем — его дети (и их дети, если есть)
                if ($node->relationLoaded('children') && $node->children->isNotEmpty()) {
                    foreach ($node->children as $child) {
                        $walk($child);
                    }
                }
            };
            foreach ($roots as $root) {
                $walk($root);
            }
            $items = $flat->values()->all();

            $currentSlug = request()->routeIs('catalog.index', 'localized.catalog.index')
                ? 'pies'
                : (request()->route('slug') ?? request()->route('categorySlug'));

            $activeIndex = null;
            foreach ($items as $i => $it) {
                if ($currentSlug === null && $it['slug'] === null) { $activeIndex = $i; break; }
                if ($currentSlug !== null && $it['slug'] === $currentSlug) { $activeIndex = $i; break; }
            }

            $MainMenuItems=$items;
            $MenuactiveIndex=$activeIndex;
            $MenuBrand='';
            // Передаём в partial
            $view->with(compact('MainMenuItems', 'MenuactiveIndex', 'MenuBrand'));
        });
    }

    protected function menuProductCounts(): array
    {
        $cacheKey = app(CatalogCacheService::class)->key('menu_product_counts');

        return Cache::remember($cacheKey, now()->addMinutes(5), function (): array {
            $products = Product::query()
                ->active()
                ->mainProduct()
                ->where(function ($query) {
                    $query->whereNull('is_imported')
                        ->orWhere('is_imported', false);
                })
                ->select(['id', 'category_id'])
                ->with([
                    'mainCategory:id,slug',
                    'categories:id,slug',
                ])
                ->get();

            $counts = [];

            foreach ($products as $product) {
                $slugs = collect([
                    $product->mainCategory?->slug,
                    ...$product->categories->pluck('slug')->all(),
                ])->filter()->unique();

                foreach ($slugs as $slug) {
                    $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                }
            }

            return $counts;
        });
    }
}
