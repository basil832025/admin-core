<?php
namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class FixedDiscount extends Model
{
use SoftDeletes;

protected $table = 'bs_shop_fixed_discounts';

protected $fillable = [
'name',
'percent',
'is_active',
'applies_to',
'applies_payload',
'starts_at',
'ends_at',
'note',
];

protected $casts = [
'percent'        => 'decimal:2',
'is_active'      => 'boolean',
'applies_payload'=> 'array',
'starts_at'      => 'datetime',
'ends_at'        => 'datetime',
];

/** Скоуп: активные по флагу и по окну дат */
public function scopeActive(Builder $q): Builder
{
return $q->where('is_active', true)
->where(function ($q) {
$now = Carbon::now();
$q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
})
->where(function ($q) {
$now = Carbon::now();
$q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
});
}
    /** Сколько списать с ВЕСЬГО заказа (отрицательное число) */
    public function calculateAmountForOrder(Order $order): float
    {
        $sum = $order->items->sum(fn($i) => $i->unit_price * $i->qty);

        // сначала процент, если задан; иначе фикс. сумма; иначе 0
        if ($this->percent && (float)$this->percent > 0) {
            return round(-1 * $sum * ((float)$this->percent / 100), 2);
        }

        if ($this->amount && (float)$this->amount > 0) {
            // не даём скидке «перекрыть» сумму
            return round(-1 * min((float)$this->amount, $sum), 2);
        }

        return 0.0;
    }

    public function toAdjustmentMeta(): array
    {
        return [
            'percent' => (float)($this->percent ?? 0),
            'amount'  => (float)($this->amount ?? 0),
        ];
    }
/** Скоуп: применимые «для всех» (текущий этап) */
public function scopeForAll(Builder $q): Builder
{
return $q->where('applies_to', 'all');
}

/** Удобный аксессор для отображения, напр. "Именинники (−20%)" */
public function getDisplayLabelAttribute(): string
{
$p = number_format((float)$this->percent, 2, '.', '');
return "{$this->name} (−{$p}%)";
}

/**
* Заглушка-валидатор бизнес-логики применения.
* Позже расширим проверками по клиенту/сегменту.
*/
public function canApply(?int $clientId = null): bool
{
if (!$this->is_active) return false;

$now = now();
if ($this->starts_at && $now->lt($this->starts_at)) return false;
if ($this->ends_at && $now->gt($this->ends_at)) return false;

// На данном этапе — только "all"
return $this->applies_to === 'all';
}
}
