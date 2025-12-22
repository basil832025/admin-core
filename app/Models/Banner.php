<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Banner extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasTranslations;

    protected $table = 'bs_banners';

    protected $fillable = [
        'title',
        'subtitle',
        'button_text',
        'image',          // универсальная
        'images',         // JSON с локальными
        'image_mobile',
        'url',
        'target',
        'sort',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /** Поля, которые будут храниться как JSON переводы */
    public array $translatable = [
        'title',
        'subtitle',
        'button_text',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'images'    => 'array',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];
    /**
     * Вернуть путь картинки для конкретного языка с fallback на универсальную.
     */

    public function getImageForLocale(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $images = $this->images ?? [];

        // сначала ищем локализованную
        if (!empty($images[$locale])) {
            return $images[$locale];
        }

        // иначе универсальная
        return $this->image;
    }
}
