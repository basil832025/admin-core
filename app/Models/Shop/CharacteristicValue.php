<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;
class CharacteristicValue extends Model
{
    use HasTranslations;
    protected $table = 'bs_characteristic_values';
    protected $fillable = [
        'characteristic_id',
        'value',
        'sort_order',
        'is_active',
    ];
    protected $casts = [
        'value'          => 'array',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];
    public $translatable = [
        'value',

    ];
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_characteristic_value',
            'characteristic_value_id', // текущая модель -> pivot
            'product_id'               // целевая модель -> pivot
        )->withPivot([
            'characteristic_id',
            'price_modifier',
            'value_text',
            'value_number',
            'value_datetime',
        ]);
    }
    public function productValues()
    {
        return $this->hasMany(ProductCharacteristicValue::class, 'characteristic_value_id');
    }
    public function option(): BelongsTo
    {
        return $this->belongsTo(CharacteristicValue::class, 'characteristic_value_id');
    }
    /**
     * Если используешь spatie/laravel-translatable и у модели есть метод getTranslation(),
     * можно оставить и такой хелпер:
     */
    public function getValueFor(string $locale): ?string
    {
        if (method_exists($this, 'getTranslation')) {
            return $this->getTranslation('value', $locale);
        }

        $arr = $this->value;
        if (!is_array($arr)) $arr = json_decode((string) $this->value, true) ?: [];
        return $arr[$locale]
            ?? $arr['uk'] ?? $arr['en'] ?? $arr['ru']
            ?? (is_array($arr) && $arr ? reset($arr) : null);
    }
    /**
     * Удобный ярлык для вывода: значение на текущей локали.
     * Фолбэки: uk -> en -> ru -> первый элемент массива.
     */
    public function getLabelAttribute(): ?string
    {
        $arr = $this->value;

        if (!is_array($arr)) {
            $arr = json_decode((string) $this->value, true) ?: [];
        }

        $loc = app()->getLocale(); // 'uk' по умолчанию у тебя

        return $arr[$loc]
            ?? $arr['uk']
            ?? $arr['en']
            ?? $arr['ru']
            ?? (is_array($arr) && $arr ? reset($arr) : null);
    }
    public function characteristic()
    {
        return $this->belongsTo(Characteristic::class);
    }
    public function variations(): BelongsToMany
    {
        return $this->belongsToMany(
            Variation::class,
            'variation_characteristic_value',
            'characteristic_value_id',
            'variation_id'
        );
    }
}
