<?php

use App\Models\Pages;
use Illuminate\Support\Facades\Cache;

if (! function_exists('page_field')) {
    /**
     * Универсальный геттер полей страницы: текст/HTML и изображения.
     *
     * @param string      $pageSlug   slug страницы (напр. 'about')
     * @param string      $fieldSlug  slug блока/поля (напр. 'about_15' или 'about_img1')
     * @param mixed       $default    значение по умолчанию
     * @param string|null $locale     локаль (если null — берём app()->getLocale())
     */
    function page_field(string $pageSlug, string $fieldSlug, $default = null, ?string $locale = null)
    {
        /** @var Pages|null $page */
        $page = Cache::remember("page:$pageSlug", 30, fn () => //3600 - 1 час
        Pages::query()->where('slug', $pageSlug)->first()
        );
        if (! $page) return $default;

        // Нормализация локали
        $loc = $locale ?: app()->getLocale();
        $loc = strtolower($loc);
        $loc = explode('-', str_replace('_', '-', $loc))[0] ?: $loc;
        if ($loc === 'ua') $loc = 'uk';

        $fields = $page->fields ?? [];

        // Если структура не список, попробуем прямой доступ по ключу
        if (is_array($fields) && isset($fields[$fieldSlug])) {
            $direct = $fields[$fieldSlug];

            // Простой текст
            if (is_string($direct) && trim($direct) !== '') {
                return $direct;
            }
        }

        // Найдём нужный узел в списке
        $node = collect($fields)->first(function ($item) use ($fieldSlug) {
            return data_get($item, 'slug') === $fieldSlug
                || data_get($item, 'data.slug') === $fieldSlug;
        });

        if ($node === null) return $default;

        // Если это image-узел
        $type = data_get($node, 'type') ?: data_get($node, 'data.type');
        if ($type === 'image') {
            $imagePath = data_get($node, 'data.image') ?: data_get($node, 'image');
            if (! $imagePath) return $default;

            $imagePath = ltrim((string) $imagePath, '/');
            if ($imagePath === '') return $default;

            if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
                return $imagePath;
            }

            if (str_starts_with($imagePath, 'storage/')) {
                return '/' . $imagePath;
            }

            return asset('storage/' . ltrim($imagePath, '/'));
        }

        // Текстовые узлы — ищем в values / data.values
        $vals = data_get($node, 'values') ?? data_get($node, 'data.values');

        // Возможные пути до текста
        $candidates = [
            "content.$loc",   // values.content.uk
            $loc,             // values.uk
            "value.$loc",     // values.value.uk
            'content',        // values.content (string)
            'value',          // values.value (string)
            null,             // сам $vals как строка
        ];

        foreach ($candidates as $path) {
            $val = $path ? data_get($vals, $path) : (is_string($vals) ? $vals : null);
            if (is_string($val) && trim($val) !== '') return $val;
        }

        return $default;
    }
}
