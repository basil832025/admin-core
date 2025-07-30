<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Arr;
use App\Models\Setting;
use App\Models\CharacteristicValue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Characteristic extends Model
{
    use HasFactory;
    use HasTranslations;
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'pricing_type',
        'sort_order',
        'expand_values',
        'is_required',
        'field_type',
        'is_active',
    ];
    protected $casts = [
        'name'          => 'array',
        'pricing_type'  => 'integer',
        'sort_order'    => 'integer',
        'expand_values' => 'boolean',
        'is_required'   => 'boolean',
        'is_active'     => 'boolean',
    ];
    public function productValues()
    {
        return $this->hasMany(ProductCharacteristicValue::class);
    }
// App\Models\Characteristic.php

    public function categories()
    {
        return $this->belongsToMany(
            \App\Models\ProductCategory::class,
            'category_characteristic',
            'characteristic_id',
            'category_id'
        );
    }

    public function category()
    {
        return $this->belongsTo(CharacteristicCategory::class, 'category_id');
    }
    public $translatable = [
        'name',

    ];
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_characteristic_value')
            ->withTimestamps();
    }
    public function values(): HasMany
    {
        return $this->hasMany(CharacteristicValue::class, 'characteristic_id');
    }
    // генерация slug  при создании и для текущего языка по умолчанию
    protected static function booted(): void
    {
        static::saving(function (Characteristic $post) {

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
}
