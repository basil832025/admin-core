<?php

namespace App\Models\Shop;

use App\Services\CatalogCacheService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCharacteristicValue extends Model
{
    protected $table = 'bs_product_characteristic_value'; // 👈 Явно указываем имя таблицы

    protected $fillable = [
        'product_id',
        'characteristic_id',
        'characteristic_value_id',
        'price_modifier',
        'value_text',
        'value_number',
        'value_datetime', // ← важно!
    ];

    protected $casts = [
        'value' => 'json', // так как у тебя многоязычные значения
        'value_datetime' => 'datetime',
        'value_number' =>   'int',
        'price_modifier' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            app(CatalogCacheService::class)->bump();
        });

        static::deleted(function (): void {
            app(CatalogCacheService::class)->bump();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

   /* public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class);
    }*/
    public function characteristic()
    {
        return $this->belongsTo(Characteristic::class, 'characteristic_id');
    }

    // 👇 ЯВНО: связь на справочник значений
    public function characteristicValue()
    {
        return $this->belongsTo(CharacteristicValue::class, 'characteristic_value_id');
    }

}
