<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCalculationItem extends Model
{
    protected $table = 'product_calculation_items';

    protected $fillable = [
        'calculation_id', 'component_product_id',
        'qty', 'unit_cost', 'row_cost',
    ];

    protected $casts = [
        'qty'       => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'row_cost'  => 'decimal:2',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(ProductCalculation::class, 'calculation_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /** Авторасчет суммы перед сохранением */
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            $m->row_cost = (float) $m->qty * (float) $m->unit_cost;
        });

        static::saved(function (self $m) {
            $m->calculation?->recalcTotals();
        });

        static::deleted(function (self $m) {
            $m->calculation?->recalcTotals();
        });
    }
}
