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

        $cacheKey = "st:$group:$slug:$locale";
        $text = Cache::remember($cacheKey, 3600, function () use ($slug, $group, $locale) {
            $row = SiteText::query()
                ->when($group, fn($q) => $q->where('group', $group))
                ->where('slug', $slug)
                ->first();
           // dd($row);
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
