<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCharacteristicValue extends Model
{
    protected $table = 'product_characteristic_value'; // 👈 Явно указываем имя таблицы

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class);
    }
}
