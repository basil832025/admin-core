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
        static $resolvedTexts = [];

        $locale ??= app()->getLocale();
       // dump($locale);
        // Если group не указан, пытаемся парсить из slug (например, 'auth.enter_phone' -> group='auth', slug='enter_phone')
        // Если slug содержит точку и group не указан, используем slug как есть (полный путь)
        if (!$group && strpos($slug, '.') !== false) {
            // Если slug уже содержит группу (например, 'auth.enter_phone'), используем его как полный slug
            $fullSlug = $slug;
        } else {
            $fullSlug = $group ? "$group.$slug" : $slug;
        }

        $cacheKey = "st:$fullSlug:$locale";
        $requestMemoKey = $fullSlug.'|'.$locale;

        if (array_key_exists($requestMemoKey, $resolvedTexts)) {
            $result = $resolvedTexts[$requestMemoKey] ?? $default ?? $slug;

            return $result !== null ? (string) $result : '';
        }

        $text = Cache::remember($cacheKey, 3600, function () use ($fullSlug, $locale) {
            $row = SiteText::query()
                ->where('slug', $fullSlug)
                ->first();
            $translated = $row?->getTranslation('value', $locale);
            // Если текст содержит HTML entities, декодируем их перед экранированием
            // чтобы избежать двойного экранирования
            if ($translated && is_string($translated)) {
                $decoded = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                // Проверяем, что декодирование что-то изменило (были HTML entities)
                if ($decoded !== $translated) {
                    $translated = $decoded;
                }
            }
            return $translated;
        });

        // Возвращаем неэкранированный текст, так как Blade {{ }} сам экранирует
        // Это предотвращает двойное экранирование
        $result = $text ?? $default ?? $slug;
        $resolvedTexts[$requestMemoKey] = $result;
        if ($result !== null) {
            return (string)$result;
        }
        return '';
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
