<?php

namespace App\Providers;

use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use Illuminate\Support\Facades\View;
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
            $walk = function ($node) use (&$walk, &$flat, $locale) {
                // сам узел
                $flat->push([
                    'id'    => $node->id,
                    'label' => $node->getTranslation('title', $locale),
                    'slug'  => $node->slug,
                    'url'   => '/'.$node->slug,
                    'order' => (int) ($node->order ?? 0),
                    'count' => $this->countProductsForCategory($node->slug),
                ]);
                // если родителький слаг Все пироги то выведим Хиты и новинки
                if ($node->slug=='pies')
                {
                    $flat->push([
                        'id'    => 5000,
                        'label' => st('menu.hits','Хіти'),
                        'slug'  => 'pies_hits',
                        'url'   => '/pies_hits',
                        'order' => (int) ($node->order ?? 0),
                    ]);
                    $flat->push([
                        'id'    => 5001,
                        'label' => st('menu.news','Новинки'),
                        'slug'  => 'pies_news',
                        'url'   => '/pies_news',
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

// активный индекс (по slug из роута)
            $currentSlug = request()->route('slug');
            $activeIndex = 0;
            foreach ($items as $i => $it) {
                if (($it['slug'] ?? null) === $currentSlug) { $activeIndex = $i; break; }
            }
            // Добавляем "Все пироги" в начало
        /*    array_unshift($items, [
                'label' => __('menu.all_pies'),
                'url'   => route('catalog.index'),
                'slug'  => null,
            ]);*/

            // Определяем активный пункт
            $currentSlug = request()->route('slug');
            $activeIndex = 0;
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
    protected function countProductsForCategory($slug): int
    {
     //   $slug = $category->slug;


        return Product::query()
            ->active()
            ->cardSelect()
            ->mainProduct()
            ->where(function ($q) use ($slug) {
                $q->whereHas('categories', fn($qq) => $qq->where('slug', $slug))
                    ->orWhereHas('mainCategory', fn($qq) => $qq->where('slug', $slug));
            })
            ->count();
    }
}
