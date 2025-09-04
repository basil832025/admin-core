<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Setting;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Str;
class Blog extends Model
{
    use HasTranslations;
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
    ];
    public $translatable = [
        'title',
        'content',
    ];
    // генерация slug  при создании и для текущего языка по умолчанию
    protected static function booted(): void
    {
        static::saving(function (Blog $post) {

            // проверяем, что slug ещё пустой и есть title
            if ($post->slug) {
                return;
            }

            // сравниваем с дефолтной локалью
            $defaultLocale = Setting::value('default_language_code')
                ?: config('app.locale');
          /*  dd(app()->getLocale());
            if (app()->getLocale() !== $defaultLocale) {
                return;
            }*/

            // выбираем строку из массива по локали
            $locale = $defaultLocale;
            $titleForSlug = is_array($post->title)
                ? ($post->title[$locale] ?? reset($post->title))
                : $post->title;

            if ($titleForSlug) {
                $post->slug = Str::slug($titleForSlug);
            }
        });
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
}
