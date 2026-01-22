<?php
namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;


class FixedDiscount extends Model
{
use SoftDeletes;
    use HasTranslations;
protected $table = 'bs_shop_fixed_discounts';


protected $fillable = [
'name',
'description',
'percent',
'is_active',
'applies_to',
'applies_payload',
'starts_at',
'ends_at',
'note',
];
    public $translatable = [
        'name', 'description',
    ];
protected $casts = [
'percent'        => 'decimal:2',
'is_active'      => 'boolean',
'applies_payload'=> 'array',
'description'=> 'array',
'starts_at'      => 'datetime',
'ends_at'        => 'datetime',
'name' => 'array',
];
    /**
     * Получить название акции для конкретной локали.
     */
    public function getNameForLocale(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $names  = $this->name;

        // fallback: если в БД ещё лежит просто строка
        if (! is_array($names)) {
            return (string) $names;
        }

        return $names[$locale]
            ?? ($names[config('app.fallback_locale')] ?? null)
            ?? reset($names)
            ?? '';
    }

    /** Удобный аксессор для отображения, напр. "Именинники (−20%)" */
    public function getDisplayLabelAttribute(): string
    {
        $p    = number_format((float) $this->percent, 2, '.', '');
        $name = $this->getNameForLocale();

        return "{$name} (−{$p}%)";
    }
    /**
     * Сколько скидки (положительное число) дать с указанной суммы.
     * Используется на checkout для живого пересчёта.
     */
    public function calculateForTotal(float $sum): float
    {
        if ($sum <= 0) {
            return 0.0;
        }

        // 1) если задан percent — считаем процент
        $percent = (float) ($this->percent ?? 0);
        if ($percent > 0) {
            return round($sum * $percent / 100, 2);
        }

        // 2) иначе, если задана фикс. сумма
        $amount = (float) ($this->amount ?? 0);
        if ($amount > 0) {
            // не даём скидке быть больше суммы
            return round(min($amount, $sum), 2);
        }

        // 3) иначе — никакой скидки
        return 0.0;
    }
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
    public function hasEligibleProducts(Collection $productIds): bool
    {
        $ids = $productIds->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return false;
        }

        // на текущем этапе — глобальная скидка "для всех"
        // позже сюда по аналогии можно добавить проверки по товарам/категориям
        return true;
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
