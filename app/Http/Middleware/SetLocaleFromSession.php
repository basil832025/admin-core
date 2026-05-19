<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class SetLocaleFromSession
{
    private const DEFAULT_LOCALES = ['ru', 'uk', 'en'];

    public function handle(Request $request, Closure $next)
    {
        // Получаем список разрешенных языков из БД или используем дефолтные
        $allowed = self::DEFAULT_LOCALES;

        try {
            if (Schema::hasTable('bs_languages')) {
                $allowed = Cache::remember('front.active_languages', now()->addHours(6), function (): array {
                    $locales = Language::query()
                        ->where('active', true)
                        ->pluck('code')
                        ->map(fn ($code) => strtolower((string) $code))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return $locales !== [] ? $locales : self::DEFAULT_LOCALES;
                });
            }
        } catch (\Exception $e) {
            // Если таблицы нет, используем дефолтные
        }

        // Если языков нет, используем дефолтные
        if (empty($allowed)) {
            $allowed = self::DEFAULT_LOCALES;
        }

        // Определяем, находимся ли мы в админке
        $isAdmin = $request->is('admin*') || $request->is('*/admin*');
        
        // Сохраняем текущий URL админки в сессии для редиректа после смены языка
        if ($isAdmin && !$request->is('admin/switch-locale*') && !$request->is('admin/clear-cache*')) {
            session(['admin_previous_url' => $request->fullUrl()]);
        }
        
        // Для админки используем отдельный ключ сессии, для фронтенда - язык из URL (если есть)
        if ($isAdmin) {
            $locale = session('admin_locale')
                ?? config('app.locale');
        } else {
            $routeLocale = $request->route('locale');
            if (is_string($routeLocale) && in_array(strtolower($routeLocale), ['ru', 'en'], true)) {
                $locale = strtolower($routeLocale);
                session(['locale' => $locale]);
                cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));
            } else {
                $locale = 'uk';
                session(['locale' => $locale]);
                cookie()->queue(cookie('locale', $locale, 60 * 24 * 365));
            }
        }

        if (is_array($locale)) {
            $locale = reset($locale) ?: null;
        }

        if (! is_string($locale) || $locale === '') {
            $locale = null;
        }
        
        $locale = strtolower($locale ?? '');

        if (! in_array($locale, $allowed, true)) {
            $locale = $allowed[0] ?? 'ru';
        }

        // Устанавливаем локаль для всего приложения
        app()->setLocale($locale);

        if (! $isAdmin) {
            if (in_array($locale, ['ru', 'en'], true)) {
                URL::defaults(['locale' => $locale]);
            } else {
                URL::defaults(['locale' => null]);
            }
        }
        
        // Также устанавливаем локаль для Carbon (даты)
        if (class_exists(\Carbon\Carbon::class)) {
            \Carbon\Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
