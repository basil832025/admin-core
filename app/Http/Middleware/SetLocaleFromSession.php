<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Closure;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next)
    {
        // Получаем список разрешенных языков из БД или используем дефолтные
        $allowed = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('bs_languages')) {
                $allowed = \App\Models\Language::where('active', true)
                    ->pluck('code')
                    ->map(fn($c) => strtolower($c))
                    ->toArray();
            }
        } catch (\Exception $e) {
            // Если таблицы нет, используем дефолтные
        }
        
        // Если языков нет, используем дефолтные
        if (empty($allowed)) {
            $allowed = ['ru', 'uk', 'en'];
        }

        $locale = session('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale');

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

        // Устанавливаем локаль для всего приложения (включая админку)
        app()->setLocale($locale);
        
        // Также устанавливаем локаль для Carbon (даты)
        if (class_exists(\Carbon\Carbon::class)) {
            \Carbon\Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
