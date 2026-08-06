<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ProductUnit extends Model
{
    use HasTranslations;

    protected $table = 'bs_product_units';

    protected $fillable = [
        'code',
        'name',
        'short_name',
        'sort_order',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'name' => 'array',
        'short_name' => 'array',
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public array $translatable = [
        'name',
        'short_name',
    ];

    protected static function booted(): void
    {
        static::saved(function (ProductUnit $unit): void {
            if (! $unit->is_default) {
                return;
            }

            static::query()
                ->whereKeyNot($unit->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }
}
