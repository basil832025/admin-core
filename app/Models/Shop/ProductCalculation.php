<?php

namespace App\Models\Shop;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCalculation extends Model
{
    protected $table = 'product_calculations';

    protected $fillable = [
        'product_id', 'valid_from', 'valid_to', 'name', 'note', 'total_cost',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
        'total_cost' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductCalculationItem::class, 'calculation_id');
    }

    /** Действует ли на конкретную дату (по умолчанию — сегодня) */
    public function isActiveOn(?\DateTimeInterface $date = null): bool
    {
        $d = CarbonImmutable::parse($date ?? now());
        $fromOk = $this->valid_from->lte($d);
        $toOk   = is_null($this->valid_to) || $this->valid_to->gte($d);
        return $fromOk && $toOk;
    }

    /** Пересчитать total_cost из позиций */
    public function recalcTotals(): void
    {
        $sum = $this->items()->sum('row_cost');
        $this->forceFill(['total_cost' => $sum])->saveQuietly();
    }
}
