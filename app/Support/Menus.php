<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Shop\ProductCategory;
use Illuminate\Support\Str;
use App\Models\Pages;

class Menus
{
    /**
     * Вернуть "плоский" массив пунктов корня (parent_id = -1) для указанного slug.
     * Формат: key, label, route (готовая href или имя роута), activeWhen, icon
     */
    public static function bySlug(string $slug): array
    {
        $menu = Menu::query()->where('slug', $slug)->first();
       // dump($menu);
        if (!$menu) return [];

        $now    = now();
        $locale = app()->getLocale();

        $items = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->where('parent_id', -1)                 // твоя договорённость с деревом
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('visible_from')->orWhere('visible_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('visible_to')->orWhere('visible_to', '>=', $now);
            })
            ->orderBy('sort')
            ->get();
      //  dd($items);
        return $items->map(function (MenuItem $i) use ($locale) {
            $title = $i->title;
            if (is_array($title)) {
                $label = $title[$locale] ?? reset($title);
            } else {
                $label = $title;
            }


          //  $label = is_array($i->title) ? ($i->title[$locale] ?? reset($i->title)) : $i->title;
            $href  = self::resolveHref($i);

            // простая автоподсветка активного: по текущему path
            $path  = trim(parse_url($href, PHP_URL_PATH) ?: '', '/');
            $activeWhen = $path ? $path . '*' : '';

            return [
                'key'        => (string) $i->id,
                'label'      => $label ?: ('#' . $i->id),
                // оставляем поле 'route' как у тебя в компоненте — кладём сюда уже готовую HREF
                // (если нужен именно route name — см. resolveHref ниже, можно переключить)
                'route'      => $href,
                'href'      => $href,
                'activeWhen' => $activeWhen,
                'auth_only'  => (bool) $i->auth_only,
                'icon'       => $i->icon ?: null,
            ];
        })->all();
    }

    /**
     * Построение ссылки по типу.
     * Сейчас: 'url' → как есть (относительный/абсолютный),
     *         остальное — можно настроить под твои реальные роуты.
     */
    protected static function resolveHref(MenuItem $i): string
    {
        // 1. Произвольный URL
        if ($i->link_type === 'url') {
            $u = trim((string) $i->url);
            if ($u === '') return '#';

            if (Str::startsWith($u, ['http://','https://','/'])) {
                return $u;
            }

            return '/' . ltrim($u, '/');
        }

        try {
          //  dump($i->link_type);
            switch ($i->link_type) {

                // ===== СТАТИЧЕСКИЕ СТРАНИЦЫ =====
                case 'page':
                    $page = Pages::find($i->target_id);
                    return $page ? '/' . ltrim($page->slug, '/') : '#';

                // ===== КАТАЛОГ: КАТЕГОРИЯ =====
                case 'category':
                    $cat = ProductCategory::find($i->target_id);
                    return $cat
                        ? '/' . ltrim($cat->slug, '/')
                        : '#';

                // ===== БЛОГ: КАТЕГОРИЯ =====
                case 'blog_category':
                    $category = BlogCategory::find($i->target_id);
                 //   dump($category);
                    return $category
                        ? '/' . ltrim($category->slug, '/')
                        : '#';

                // ===== БЛОГ: СТАТЬЯ =====
                case 'blog':
                    $post = Blog::with('category')->find($i->target_id);
                    if (!$post) return '#';

                    if ($post->category) {
                        return '/blog/'
                            . ltrim($post->category->slug, '/')
                            . '/'
                            . ltrim($post->slug, '/');
                    }

                    return '/blog/' . ltrim($post->slug, '/');
            }
        } catch (\Throwable $e) {
            // не валим фронт
        }

        return '#';
    }
}
