<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use App\Models\Setting;
use Illuminate\Support\Arr;
class CharacteristicCategory extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $table = 'bs_characteristic_categories';
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name'             => 'array',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];
    // список переводимых полей
    public $translatable = [
        'name',

    ];
    /**
     * Сгенерировать слаг автоматически, если не задан.
     */
    // генерация slug  при создании и для текущего языка по умолчанию
    protected static function booted(): void
    {
        static::saving(function (CharacteristicCategory $post) {

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
            // Берём «сырое» значение JSON
         //   getAttribute('name')
            $raw = $post->getAttributes() ?? '{}';
            //dd($raw);
            // Декодируем в массив
            $names = json_decode($raw['name'], true);
          //  dd($names);
            // выбираем строку из массива по локали
            $locale = $defaultLocale;
            // Берём по ключу локали или первый непустой
            $titleForSlug = Arr::get($names, $locale)
                ?: Arr::first($names, fn($value) => ! empty($value));
     //   dd($titleForSlug);
            if ($titleForSlug) {
                $post->slug = Str::slug($titleForSlug);
            }
        });
    }
  /*  protected static function booted()
    {
        static::saving(function (self $model) {
            if (! $model->slug && $model->name) {
                $model->slug = \Illuminate\Support\Str::slug($model->name);
            }
        });
    }*/
  /*  public function characteristics()
    {
        return $this->hasMany(Characteristic::class, 'category_id');
    }*/
}
