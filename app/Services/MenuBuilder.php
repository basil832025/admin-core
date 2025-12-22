<?php

namespace App\Services;

use App\Models\Shop\ProductCategory;
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
                'count' => $this->countProductsForCategory($c),
            ])->values();
    }

    /** Только корневые категории */
    protected function catalogRoot(): Collection
    {
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
                'count' => $this->countProductsForCategory($c),
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
    protected function countProductsForCategory(ProductCategory $category): int
    {
        $slug = $category->slug;


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
