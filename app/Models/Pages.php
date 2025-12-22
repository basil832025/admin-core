<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Facades\Cache;
class Pages extends Model
{
    use HasTranslations;
    protected $table = 'bs_pages';
    // Разрешённые для массового заполнения поля
    protected $fillable = [
        'slug',
        'title',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'status',
        'fields',
    ];

    // Приводим JSON-поля к массиву при извлечении
    protected $casts = [
        'title'            => 'array',
        'content'          => 'array',
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'meta_keywords'    => 'array',
        'fields' => 'array',
    ];
    // список переводимых полей
    public $translatable = [
        'title',
        'content',
     ];
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
    /**
     * Получить заголовок на текущем языке (код берётся из config('app.locale')).
     */
    public function getTitleForLocale(string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->title[$locale] ?? null;
    }

    /**
     * Аналогично для контента.
     */
    public function getContentForLocale(string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        return $this->content[$locale] ?? null;
    }
    // Чтение блока по слагу (под Builder)
    public function field(string $fieldSlug, ?string $locale = null, $default = null)
    {
        $locale = $locale ?: app()->getLocale();
        foreach (($this->fields ?? []) as $block) {
            $type = $block['type'] ?? null;
            $data = $block['data'] ?? [];
            if (($data['slug'] ?? null) !== $fieldSlug) continue;

            if ($type === 'image') {
                $path = $data['image'] ?? null;
                return $path ? \Storage::disk('public')->url($path) : $default;
            }

            $raw = $data['values'][$locale] ?? null; // ['content'=>...] или строка
            $val = is_array($raw) ? ($raw['content'] ?? null) : $raw;

            return $val ?? $default;
        }
        return $default;
    }
    protected static function booted(): void
    {
        static::saved(function (Pages $page) {
            Cache::forget("page:{$page->slug}");
        });

        static::deleted(function (Pages $page) {
            Cache::forget("page:{$page->slug}");
        });
    }
}
