<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
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
    ];

    // Приводим JSON-поля к массиву при извлечении
    protected $casts = [
        'title'            => 'array',
        'content'          => 'array',
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'meta_keywords'    => 'array',
    ];
    // список переводимых полей
    public $translatable = [
        'title',
        'content',
     ];
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
}
