<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CharacteristicValue extends Model
{
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
