<?php

use App\Models\SiteText;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

if (! function_exists('st')) {
    /**
     * Return a localized site text. Blade {{ }} escapes it; use st_raw() for HTML.
     */
    function st(string $slug, ?string $default = null, ?string $locale = null, ?string $group = null): string
    {
        static $resolvedTexts = [];
        static $localizedTexts = [];

        $locale ??= app()->getLocale();

        if (! $group && strpos($slug, '.') !== false) {
            $fullSlug = $slug;
        } else {
            $fullSlug = $group ? "$group.$slug" : $slug;
        }

        $requestMemoKey = $fullSlug.'|'.$locale;

        if (array_key_exists($requestMemoKey, $resolvedTexts)) {
            $result = $resolvedTexts[$requestMemoKey] ?? $default ?? $slug;

            return $result !== null ? (string) $result : '';
        }

        if (! array_key_exists($locale, $localizedTexts)) {
            $localizedTexts[$locale] = Cache::remember("st:all:$locale", 3600, function () use ($locale): array {
                return SiteText::query()
                    ->get(['slug', 'value'])
                    ->mapWithKeys(function (SiteText $row) use ($locale): array {
                        $translated = $row->getTranslation('value', $locale);

                        if ($translated && is_string($translated)) {
                            $decoded = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            if ($decoded !== $translated) {
                                $translated = $decoded;
                            }
                        }

                        return [(string) $row->slug => $translated];
                    })
                    ->all();
            });
        }

        $text = is_array($localizedTexts[$locale] ?? null)
            ? ($localizedTexts[$locale][$fullSlug] ?? null)
            : null;

        $result = $text ?? $default ?? $slug;
        $resolvedTexts[$requestMemoKey] = $result;

        return $result !== null ? (string) $result : '';
    }
}

if (! function_exists('st_raw')) {
    /**
     * Return a localized site text as raw HTML.
     */
    function st_raw(string $slug, ?string $default = null, ?string $locale = null, ?string $group = null): HtmlString
    {
        return new HtmlString(st($slug, $default, $locale, $group));
    }
}
