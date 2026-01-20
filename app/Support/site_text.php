<?php
/*
 {{-- Экранированный текст --}}
{{ st('header.menu.all_pies') }}

{{-- HTML (если в админке хранится с тегами) --}}
{!! st_raw('footer.contacts_html') !!}
 *
 */
use App\Models\SiteText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

if (! function_exists('st')) {
    /**
     * Безопасный вывод (экранированный).
     */
    function st(string $slug, ?string $default = null, ?string $locale = null, ?string $group = null): string
    {
        $locale ??= app()->getLocale();

        // Если group не указан, пытаемся парсить из slug (например, 'auth.enter_phone' -> group='auth', slug='enter_phone')
        // Если slug содержит точку и group не указан, используем slug как есть (полный путь)
        if (!$group && strpos($slug, '.') !== false) {
            // Если slug уже содержит группу (например, 'auth.enter_phone'), используем его как полный slug
            $fullSlug = $slug;
        } else {
            $fullSlug = $group ? "$group.$slug" : $slug;
        }

        $cacheKey = "st:$fullSlug:$locale";
        $text = Cache::remember($cacheKey, 3600, function () use ($fullSlug, $locale) {
            $row = SiteText::query()
                ->where('slug', $fullSlug)
                ->first();
            return $row?->getTranslation('value', $locale);
        });

        return e($text ?? $default ?? $slug);
    }
}

if (! function_exists('st_raw')) {
    /**
     * Сырый HTML (не экранируется).
     */
    function st_raw(string $slug, ?string $default = null, ?string $locale = null, ?string $group = null): HtmlString
    {
        $html = st($slug, $default, $locale, $group);
        return new HtmlString($html);
    }
}
