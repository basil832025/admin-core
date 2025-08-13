<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;
class CharacteristicValue extends Model
{
    use HasTranslations;
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
    public function productValues()
    {
        return $this->hasMany(ProductCharacteristicValue::class, 'characteristic_value_id');
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
