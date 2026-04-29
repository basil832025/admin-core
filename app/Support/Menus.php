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
            // Используем getTranslation из HasTranslations для правильного извлечения перевода
            if (method_exists($i, 'getTranslation') && method_exists($i, 'isTranslatableAttribute') && $i->isTranslatableAttribute('label')) {
                $label = $i->getTranslation('label', $locale, false); // false = без fallback
                if (empty($label)) {
                    // Fallback на другие локали
                    $label = $i->getTranslation('label', 'uk', false)
                        ?? $i->getTranslation('label', 'ru', false)
                        ?? $i->getTranslation('label', 'en', false);
                }
            } else {
                // Если нет HasTranslations, работаем напрямую с массивом
                $labelRaw = $i->label;
                if (is_array($labelRaw)) {
                    $label = $labelRaw[$locale] 
                        ?? $labelRaw['uk'] 
                        ?? $labelRaw['ru'] 
                        ?? $labelRaw['en'] 
                        ?? reset($labelRaw);
                } else {
                    $label = $labelRaw;
                }
            }
            $href  = self::resolveHref($i);

            // простая автоподсветка активного: по текущему path
            $path  = trim(parse_url($href, PHP_URL_PATH) ?: '', '/');
            // Формируем паттерн для подсветки: путь + * для подпутей.
            // Для /profile (включая локализованный /ru/profile, /en/profile)
            // делаем точное совпадение, чтобы не подсвечивать /profile/orders и т.п.
            $isProfileRoot = (bool) preg_match('#(^|/)profile$#', $path);
            if ($isProfileRoot) {
                $activeWhen = $path;
            } else {
                $activeWhen = $path ? $path . '*' : '';
            }

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
        $locale = app()->getLocale();
        $prefix = in_array($locale, ['ru', 'en'], true) ? '/' . $locale : '';

        $withLocalePrefix = static function (string $path) use ($prefix): string {
            $normalized = '/' . ltrim($path, '/');
            if ($prefix === '') {
                return $normalized;
            }

            if (preg_match('#^/(ru|en)(/|$)#i', $normalized)) {
                return $normalized;
            }

            return $prefix . $normalized;
        };

        // 1. Произвольный URL
        if ($i->link_type === 'url') {
            $u = trim((string) $i->url);
            if ($u === '') return '#';

            if (Str::startsWith($u, ['http://','https://'])) {
                return $u;
            }

            if (Str::startsWith($u, ['/'])) {
                return $withLocalePrefix($u);
            }

            // Для относительных путей в profile-menu добавляем префикс /profile/
            // Это нужно для пункта "Адреса доставки" (URL: addresses -> /profile/addresses)
            // Но если URL уже = 'profile', то возвращаем просто '/profile' без дублирования
            $menu = $i->menu ?? Menu::find($i->menu_id);
            if ($menu && $menu->slug === 'profile-menu') {
                // Если URL уже = 'profile', возвращаем просто '/profile'
                if ($u === 'profile') {
                    return $withLocalePrefix('/profile');
                }
                // Исключения: favorites не нужно добавлять префикс /profile/
                if ($u === 'favorites') {
                    return $withLocalePrefix('/favorites');
                }
                // Для других относительных путей добавляем префикс /profile/
                return $withLocalePrefix('/profile/' . ltrim($u, '/'));
            }

            return $withLocalePrefix('/' . ltrim($u, '/'));
        }

        try {
          //  dump($i->link_type);
            switch ($i->link_type) {

                // ===== СТАТИЧЕСКИЕ СТРАНИЦЫ =====
                case 'page':
                    $page = Pages::find($i->target_id);
                    return $page ? $withLocalePrefix('/' . ltrim($page->slug, '/')) : '#';

                // ===== КАТАЛОГ: КАТЕГОРИЯ =====
                case 'category':
                    $cat = ProductCategory::find($i->target_id);
                    return $cat
                        ? $withLocalePrefix('/' . ltrim($cat->slug, '/'))
                        : '#';

                // ===== БЛОГ: КАТЕГОРИЯ =====
                case 'blog_category':
                    $category = BlogCategory::find($i->target_id);
                 //   dump($category);
                    return $category
                        ? $withLocalePrefix('/' . ltrim($category->slug, '/'))
                        : '#';

                // ===== БЛОГ: СТАТЬЯ =====
                case 'blog':
                    $post = Blog::with('category')->find($i->target_id);
                    if (!$post) return '#';

                    if ($post->category) {
                        return $withLocalePrefix('/blog/'
                            . ltrim($post->category->slug, '/')
                            . '/'
                            . ltrim($post->slug, '/'));
                    }

                    return $withLocalePrefix('/blog/' . ltrim($post->slug, '/'));
            }
        } catch (\Throwable $e) {
            // не валим фронт
        }

        return '#';
    }
}
