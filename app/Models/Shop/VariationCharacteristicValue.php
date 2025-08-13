<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariationCharacteristicValue extends Model
{
    protected $table = 'variation_characteristic_value';

    protected $fillable = [
        'variation_id',
        'characteristic_id',
        'characteristic_value_id',
    ];

    public $timestamps = false;

    /**
     * Связь с вариацией
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(Variation::class);
    }

    /**
     * Связь с характеристикой (например, "Размер")
     */
    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(Characteristic::class);
    }

    /**
     * Связь со значением характеристики (например, "30 см")
     */
    public function characteristicValue(): BelongsTo
    {
        return $this->belongsTo(CharacteristicValue::class);
    }
}
