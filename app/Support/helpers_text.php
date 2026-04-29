<?php
/*
 * Примеры использования в Blade:
{{-- 1. Полностью удалить HTML --}}
{{ clean_html($page->content) }}

{{-- 2. Безопасно очистить, но оставить <p>, <b>, <i> --}}
{!! clean_html($page->content, 'safe') !!}

{{-- 3. Разрешить только <b> и <i> --}}
{!! clean_html($page->content, 'safe', null, '<b><i>') !!}

{{-- 4. Убрать теги и ограничить 150 символами --}}
{{ clean_html($page->excerpt, 'plain', 150) }}

{{-- 5. Вывести "сыро" (только если доверяешь контенту) --}}
{!! clean_html($page->content, 'raw') !!}*/
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier; // если установлен пакет mews/purifier

if (! function_exists('clean_html')) {
    /**
     * Универсальный хелпер для очистки текста из редактора.
     *
     * @param  string|null  $value        Текст с HTML
     * @param  string       $mode         Режим очистки:
     *                                    - 'plain' → удалить все теги
     *                                    - 'safe'  → очистить опасные теги, оставить базовые
     *                                    - 'raw'   → вывести как есть (осторожно!)
     * @param  int|null     $limit        Ограничить длину текста (опционально)
     * @param  string|null  $allowedTags  Разрешённые HTML-теги для режима safe (например, '<p><b><i>')
     * @return string
     */
    function clean_html(?string $value, string $mode = 'plain', ?int $limit = null, ?string $allowedTags = null): string
    {
        if (! $value) return '';

        $text = $value;

        switch ($mode) {
            case 'plain':
                // Полностью удалить HTML-теги
                $text = strip_tags($text);
                break;

            case 'safe':
                // Безопасный вывод с очисткой
                if (class_exists(Purifier::class)) {
                    // Используем Purifier, если установлен
                    $text = Purifier::clean($text);
                } else {
                    // fallback — оставить только разрешённые или базовые теги
                    $allowed = $allowedTags ?: '<p><b><i><strong><em><ul><ol><li><br>';
                    $text = strip_tags($text, $allowed);
                }
                break;

            case 'raw':
                // ничего не очищаем
                break;
        }

        if ($limit) {
            $text = Str::limit($text, $limit);
        }

        // Fix relative media URLs like src="storage/..." on localized routes (/ru, /en)
        $text = preg_replace('/\b(src|href)=(["\'])storage\//i', '$1=$2/storage/', $text) ?? $text;

        return trim($text);
    }
}
