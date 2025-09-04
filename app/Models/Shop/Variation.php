<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
class Variation extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Получить значения характеристик этой вариации
     */
    public function characteristicValues(): BelongsToMany
    {
        return $this->belongsToMany(
            CharacteristicValue::class,
            'variation_characteristic_value',
            'variation_id',
            'characteristic_value_id'
        )->withPivot('characteristic_id');
    }
    // Автоматическая генерация slug
    protected static function booted(): void
    {

        static::saving(function (Variation $variation) {

            // проверяем, что slug ещё пустой и есть title
            if ($variation->slug) {
                return;
            }

      //  static::creating(function (Variation $variation) {
            if (empty($variation->slug) && !empty($variation->name)) {
                $variation->slug = Str::slug($variation->name);
            }
        });
    }
    public function categories()
    {
        return $this->belongsToMany(ProductCategory::class, 'category_variation');
    }
    public function variationCharacteristicValues(): HasMany
    {
        return $this->hasMany(VariationCharacteristicValue::class);
    }
    /**
     * Связь с товарами, в которых используется эта вариация
     */
    public function productVariations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }
}
