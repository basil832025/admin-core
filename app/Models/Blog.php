<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Str;
use App\Models\BlogCategory;

class Blog extends Model
{

    use HasTranslations;
    protected $table = 'bs_blogs';
    protected $fillable = [
        'blog_category_id',
        'title',
        'slug',
        'anons',
        'content',
        'preview_image',
        'detail_image',
        'tags',
        'is_published',
        'published_at',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'preview_image_i18n',
        'detail_image_i18n'
    ];
    protected $casts = [
        'title'            => 'array',
        'anons'          => 'array',
        'content'          => 'array',
        'preview_image'    => 'string',
        'detail_image'     => 'string',
        'tags'             => 'array',   // <- теги как массив
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'meta_keywords'    => 'array',
        'is_published'     => 'boolean',
        'published_at'     => 'datetime',
        'preview_image_i18n'  => 'array',
        'detail_image_i18n'   => 'array',
    ];
    public $translatable = [
        'title',
        'anons',
        'content',
    ];
    // генерация slug  при создании и для текущего языка по умолчанию
    protected static function booted(): void
    {
        static::saving(function (Blog $post) {

            // проверяем, что slug ещё пустой и есть title
            if (filled($post->slug)) {
                $post->slug = Str::slug((string) $post->slug);

                if ($post->slug !== '') {
                    return;
                }
            }

            // сравниваем с дефолтной локалью
            $defaultLocale = Setting::value('default_language_code')
                ?: config('app.locale');
          /*  dd(app()->getLocale());
            if (app()->getLocale() !== $defaultLocale) {
                return;
            }*/

            // выбираем строку из массива по локали
            $titleForSlug = static::titleForSlug($post, $defaultLocale);
            $slug = Str::slug($titleForSlug ?: 'blog-' . Str::random(8));

            if ($slug === '') {
                $slug = 'blog-' . Str::lower(Str::random(8));
            }

            $post->slug = static::uniqueSlug($slug, $post->getKey());
        });
    }

    private static function titleForSlug(Blog $post, string $preferredLocale): ?string
    {
        $translations = $post->getTranslations('title');
        $preferred = $translations[$preferredLocale] ?? null;

        if (is_string($preferred) && trim($preferred) !== '') {
            return $preferred;
        }

        foreach ($translations as $translation) {
            if (is_string($translation) && trim($translation) !== '') {
                return $translation;
            }
        }

        $title = $post->getAttribute('title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    private static function uniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
    // Удобные геттеры с фолбеком на дефолт:
    public function previewImage(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $map = (array) ($this->preview_image_i18n ?? []);
        return $map[$locale] ?? $this->preview_image; // JSON по языку или дефолт
    }

    public function detailImage(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $map = (array) ($this->detail_image_i18n ?? []);
        return $map[$locale] ?? $this->detail_image;
    }

    public function comments()
    {
        return $this->hasMany(BlogComment::class, 'blog_id', 'id');
    }
    // Категория
    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }
    /* Скоупы */
    public function scopePublished($q)
    {
        return $q->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
    // атрибут путь к изображению анонса
    protected function previewImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
            $path = $this->previewImage();
 
            if (!$path) {
                return null;
            }

            // если уже абсолютный URL — возвращаем как есть
            if (str_starts_with($path, 'http')) {
                return $path;
            }

            // если путь относительный — строим URL через disk
            return Storage::disk('public')->url($path);
        },
        );
    }
    // атрибут путь к изображению
    protected function detailImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
            $path = $this->detailImage();
 
            if (!$path) {
                return null;
            }

            // если уже абсолютный URL — возвращаем как есть
            if (str_starts_with($path, 'http')) {
                return $path;
            }

            // если путь относительный — строим URL через disk
            return Storage::disk('public')->url($path);
        },
        );
    }


    public function scopeForCategorySlug($q, string $slug)
    {
        return $q->whereHas('category', fn($qq) => $qq->where('slug', $slug));
    }
}
