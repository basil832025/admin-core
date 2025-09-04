<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Carbon\Carbon;
use Closure;

class SetLocaleFromSession
{
    public function handle($request, Closure $next)
    {
        // то, что считаем дефолтным языком проекта
        $default = Setting::value('default_language_code') ?: config('app.locale');

        // если в сессии ещё нет выбранного языка — кладём дефолт
        $locale = session('locale', $default);
        if (! session()->has('locale')) {
            session(['locale' => $locale]);
        }

        // применяем ко всему приложению
        app()->setLocale($locale);
        Carbon::setLocale($locale);

        // чтобы fallback в spatie/laravel-translatable и пр. был из БД
        config([
            'app.locale'                 => $locale,
            'app.fallback_locale'        => $default,
            'translatable.fallback_locale' => $default, // если конфиг пакета не опубликован — тоже ок
        ]);

        return $next($request);
    }
}
