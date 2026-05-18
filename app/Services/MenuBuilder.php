<?php

namespace App\Services;

use App\Models\Shop\ProductCategory;
use App\Services\CatalogCacheService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Shop\Product;

class MenuBuilder
{
    public function build(string $key): Collection
    {
        $locale = app()->getLocale();
        return Cache::remember("menu:{$key}:{$locale}", 600, function () use ($key) {
            return match ($key) {
                'catalog.flat' => $this->catalogFlat(),
                'catalog.root' => $this->catalogRoot(),
                'header.links' => $this->headerLinks(),
                'footer.links' => $this->footerLinks(),
                default => collect(),
            };
        });
    }

    /** Весь каталог «плоско», без вложенности */
    protected function catalogFlat(): Collection
    {
        $countsBySlug = $this->menuProductCounts();

        // быстрый вариант: все видимые в порядке order
        return ProductCategory::query()
            ->where('is_visible', 1)
            ->orderBy('order')
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->getTranslation('title', app()->getLocale()),
                'url'   => route('catalog.category', $c->slug),
                'slug'  => $c->slug,
                'order' => (int)($c->order ?? 0),
                'count' => (int) ($countsBySlug[$c->slug] ?? 0),
            ])->values();
    }

    /** Только корневые категории */
    protected function catalogRoot(): Collection
    {
        $countsBySlug = $this->menuProductCounts();

        return ProductCategory::query()
            ->where('is_visible', 1)
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get()
            ->map(fn ($c) => [
                'id'    => $c->id,
                'title' => $c->getTranslation('title', app()->getLocale()),
                'url'   => route('catalog.category', $c->slug),
                'slug'  => $c->slug,
                'order' => (int)($c->order ?? 0),
                'count' => (int) ($countsBySlug[$c->slug] ?? 0),
            ])->values();
    }

    /** Произвольные ссылки в шапке (пример) */
    protected function headerLinks(): Collection
    {
        return collect([
            ['title' => __('menu.promos'),   'url' => route('promos.index')],
            ['title' => __('menu.delivery'), 'url' => route('pages.show', 'delivery')],
            ['title' => __('menu.contacts'), 'url' => route('pages.show', 'contacts')],
        ]);
    }

    /** Ссылки футера (пример) */
    protected function footerLinks(): Collection
    {
        return collect([
            ['title' => __('menu.about'),   'url' => route('pages.show', 'about')],
            ['title' => __('menu.terms'),   'url' => route('pages.show', 'terms')],
            ['title' => __('menu.privacy'), 'url' => route('pages.show', 'privacy')],
        ]);
    }
    protected function menuProductCounts(): array
    {
        $cacheKey = app(CatalogCacheService::class)->key('menu_builder_product_counts');

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
