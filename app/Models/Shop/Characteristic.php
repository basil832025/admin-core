<?php
namespace App\Models\Shop;

use App\Models\SvgImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;
use Illuminate\Support\Arr;
use App\Models\Setting;
use App\Models\Shop\CharacteristicValue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Characteristic extends Model
{
    use HasFactory;
    use HasTranslations;
    protected $table = 'bs_characteristics';
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'pricing_type',
        'is_main_tab',
        'sort_order',
        'expand_values',
        'is_required',
        'field_type',
        'is_main_tab',
        'is_active',
        'svg_image_id',
    ];
    protected $casts = [
        'name'          => 'array',
        'pricing_type'  => 'integer',
        'sort_order'    => 'integer',
        'expand_values' => 'boolean',
        'is_main_tab'  => 'bool',
        'is_required'   => 'boolean',
        'is_main_tab'   => 'boolean',
        'is_active'     => 'boolean',
    ];
    public function svgImage()
    {
        return $this->belongsTo(SvgImage::class, 'svg_image_id');
    }
    public function productValues()
    {
        return $this->hasMany(ProductCharacteristicValue::class);
    }
// App\Models\Characteristic.php

    public function categories()
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'bs_category_characteristic',
            'characteristic_id',
            'category_id'
        ) ->withPivot(['is_required'])   // ← обязательно
        ->withTimestamps();;
    }

    public function characteristicCategories()
    {
        return $this->belongsTo(CharacteristicCategory::class, 'category_id');
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
        return $this->belongsToMany(Product::class, 'bs_product_characteristic_value')
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
    /** Скоупы */
    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeMainTab($q)     { return $q->where('is_main_tab', true); }
    public function scopeSorted($q)      { return $q->orderBy('sort_order'); }
}
