<?php
// app/Models/Shop/TimeDiscount.php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
class TimeDiscount extends Model
{
    use SoftDeletes;
    public const TYPE_ORDER     = 'order';      // по времени заказа
    public const TYPE_EXECUTION = 'execution';  // по времени выполнения (доставка/выдача)
    protected $table = 'shop_time_discounts';

    protected $fillable = [
        'name',
        'days',
        'time_from',
        'time_to',
        'time_type',
        'nth_item',
        'percent',
        'is_active',
        'starts_at',
        'ends_at',
        'note',
    ];

    protected $casts = [
        'days'      => 'array',     // JSON <-> array
        'percent'   => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    /** Скоуп активных по флагу и датам */
    public function scopeActive(Builder $q): Builder
    {
        $now = Carbon::now();

        return $q->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }
    public function scopeActiveForMoment(
        Builder $q,
        Carbon|string|null $moment = null,
        ?string $tz = null
    ): Builder {
        $m = $moment instanceof Carbon ? $moment->copy() : Carbon::parse($moment ?? now());
        if ($tz) $m->setTimezone($tz);

        $time    = $m->format('H:i:s');
        $weekday = (int) $m->dayOfWeekIso; // 1..7 (Mon..Sun)

        $table        = $q->getModel()->getTable();
        $hasDays      = Schema::hasColumn($table, 'days');
        $hasDaysMask  = Schema::hasColumn($table, 'days_mask');
        $hasWinStart  = Schema::hasColumn($table, 'window_start');
        $hasWinEnd    = Schema::hasColumn($table, 'window_end');

        // базовые даты
        $q->where('is_active', true)
            ->where(fn ($qq) => $qq->whereNull('starts_at')->orWhere('starts_at', '<=', $m))
            ->where(fn ($qq) => $qq->whereNull('ends_at')->orWhere('ends_at', '>=', $m));

        // === дни недели ===
        if ($hasDays) {
            // NULL или [] => все дни
            $q->where(function (Builder $qq) use ($weekday) {
                $qq->whereNull('days')
                    ->orWhereJsonLength('days', 0)
                    ->orWhereJsonContains('days', $weekday);
            });
        } elseif ($hasDaysMask) {
            // NULL или 0 => все дни
            $bit = 1 << ($weekday - 1);
            $q->where(function (Builder $qq) use ($bit) {
                $qq->whereNull('days_mask')
                    ->orWhere('days_mask', 0)
                    ->orWhereRaw('(days_mask & ?) != 0', [$bit]);
            });
        }

        // === окно времени (если обе колонки есть) ===
        if ($hasWinStart && $hasWinEnd) {
            $q->where(function (Builder $qq) use ($time) {
                $qq->whereNull('window_start')
                    ->orWhereNull('window_end')
                    // обычное окно 10:00–22:00
                    ->orWhere(function (Builder $qq) use ($time) {
                        $qq->whereColumn('window_start', '<=', 'window_end')
                            ->whereRaw('? BETWEEN window_start AND window_end', [$time]);
                    })
                    // через полночь 22:00–06:00
                    ->orWhere(function (Builder $qq) use ($time) {
                        $qq->whereColumn('window_start', '>', 'window_end')
                            ->where(function (Builder $qq) use ($time) {
                                $qq->whereRaw('? >= window_start', [$time])
                                    ->orWhereRaw('? <= window_end', [$time]);
                            });
                    });
            });
        }

        return $q;
    }
    /** Скоуп: применимых к конкретному дню недели (1=Пн..7=Вс) */
    public function scopeForWeekday(Builder $q, int $weekday): Builder
    {
        // Простой JSON_SEARCH: day входит в массив days или days = NULL (значит все дни)
        return $q->where(function ($q) use ($weekday) {
            $q->whereNull('days')
                ->orWhereRaw("JSON_CONTAINS(days, JSON_ARRAY(?))", [$weekday]);
        });
    }
    /** Сколько списать с заказа (отрицательная сумма). Учитывает canApplyNow(). */
    public function calculateAmountForOrder(Order $order): float
    {
        $percent = (float) ($this->percent ?? 0);
        if ($percent <= 0) return 0.0;

        $eachN = (int) ($this->each_n ?? 1);
        if ($eachN <= 1) $eachN = 2; // «кожна друга» по умолчанию, при желании оставь 1

        // 1) Собираем eligible позиции (примени свои фильтры при необходимости)
        $eligibleRows = $order->items->filter(function ($row) {
            // пример фильтров:
            // if (!$this->matchesProduct($row->product)) return false;
            // if (!$this->matchesCategory($row->product?->mainCategory)) return false;
            return true;
        });

        if ($eligibleRows->isEmpty()) {
            return 0.0;
        }

        // 2) Разворачиваем поштучно и берём цены единиц
        $unitPrices = [];
        foreach ($eligibleRows as $row) {
            $qty = (int) $row->qty;
            if ($qty <= 0) continue;

            // важный момент: берём исходную цену единицы, без ручных скидок
            $price = (float) $row->unit_price;

            for ($i = 0; $i < $qty; $i++) {
                $unitPrices[] = $price;
            }
        }

        $count = count($unitPrices);
        if ($count < $eachN) {
            return 0.0; // нет полной группы
        }

        // 3) Формируем группы по N штук и в каждой группе дисконтируем ДЕШЕВШИЙ
        // Удобный способ: отсортировать по убыванию и бить на чанки по N.
        // Тогда в каждом чанке последний элемент — самый дешёвый.
        rsort($unitPrices, SORT_NUMERIC); // DESC

        $discountBase = 0.0;
        $chunks = array_chunk($unitPrices, $eachN);
        foreach ($chunks as $chunk) {
            if (count($chunk) < $eachN) {
                // неполная группа — скидка не применяется
                continue;
            }
            $cheapestInGroup = min($chunk); // эквивалентно $chunk[$eachN-1] после rsort
            $discountBase += $cheapestInGroup;
        }

        // 4) Считаем скидку: только на выбранные позиции (одна в каждой полной группе)
        $amount = $discountBase * ($percent / 100);

        // скидка — отрицательное число
        return $amount > 0 ? -round($amount, 2) : 0.0;
    }
  /*  public function calculateAmountForOrder(Order $order): float
    {
        $percent = (float) ($this->percent ?? 0);
        if ($percent <= 0) return 0.0;

        $eachN = (int) ($this->each_n ?? 1);
        if ($eachN <= 0) $eachN = 1;

        // 1) Собираем eligible positions (с учётом фильтров скидки)
        $eligible = $order->items->filter(function ($item) {
            // если есть фильтры — раскомментируй/дополни
            // if (!$this->matchesProduct($item->product)) return false;
            // if (!$this->matchesCategory($item->product->mainCategory)) return false;
            // if (!$this->matchesCharacteristics($item)) return false;

            return true; // без фильтров — все позиции подходят
        });

        if ($eligible->isEmpty()) return 0.0;

        // 2) Считаем поштучно (важно для "каждый N-й")
        $units = [];
        foreach ($eligible as $row) {
            // берём реальную цену единицы (без учёта ручных скидок)
            $unitPrice = (float) $row->unit_price;
            $qty       = (int) $row->qty;

            // раскладываем поштучно, чтобы выбрать каждый N-й
            for ($i = 0; $i < $qty; $i++) {
                $units[] = $unitPrice;
            }
        }

        if (count($units) < $eachN) {
            return 0.0; // недостаточно штук, чтобы применить хотя бы раз
        }

        // 3) Выбираем каждый N-й (обычно выгоднее брать по убыванию цены, чтобы скидка была максимальной)
        // если логика должна быть "по порядку добавления", закомментируй sort.
        rsort($units, SORT_NUMERIC);

        $discount = 0.0;
        foreach ($units as $idx => $price) {
            // индексы с 0 → каждый N-й:  (idx+1) % eachN == 0
            if ( (($idx + 1) % $eachN) === 0 ) {
                $discount += $price * ($percent / 100);
            }
        }

        // 4) Возвращаем отрицательную сумму, округлённую до 2 знаков
        return $discount > 0 ? -round($discount, 2) : 0.0;
    }*/
    /** (опционально) метаданные для записи корректировки */
    public function toAdjustmentMeta(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'percent'   => (float) ($this->percent ?? 0),
            'amount'    => (float) ($this->amount ?? 0),
            'time_from' => $this->time_from,
            'time_to'   => $this->time_to,
            'days'      => $this->days,
        ];
    }
    /** Быстрый предикат: в заданном времени (HH:MM:SS) попадаем в окно? */
    public function matchesTimeWindow(?string $hhmmss): bool
    {
        if (!$hhmmss || (!$this->time_from && !$this->time_to)) {
            return true; // нет ограничений по времени
        }

        // интервал может пересекать полночь (например 22:00..02:00)
        $from = $this->time_from ?: '00:00:00';
        $to   = $this->time_to   ?: '23:59:59';

        if ($from <= $to) {
            return $hhmmss >= $from && $hhmmss <= $to;
        }

        // случай через полночь
        return ($hhmmss >= $from && $hhmmss <= '23:59:59') || ($hhmmss >= '00:00:00' && $hhmmss <= $to);
    }

    /** belongsToMany: категории (группы товаров) */
    public function categories()
    {
        // подставь фактическую модель/таблицу категории, если отличается
        return $this->belongsToMany(
            ProductCategory::class,
            'shop_time_discount_categories',
            'time_discount_id',
            'category_id'
        )->withTimestamps();
    }

    /** belongsToMany: товары */
    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'shop_time_discount_products',
            'time_discount_id',
            'product_id'
        )->withTimestamps();
    }

    /** belongsToMany: характеристики */
    public function characteristics()
    {
        return $this->belongsToMany(
            \App\Models\Shop\Characteristic::class,
            'shop_time_discount_characteristics',
            'time_discount_id',
            'characteristic_id'
        )->withTimestamps();
    }
    /** belongsToMany: Конкретные значения характеристик */
    public function characteristicValues()
    {
        return $this->belongsToMany(
            \App\Models\Shop\CharacteristicValue::class,
            'shop_time_discount_characteristic_values',
            'time_discount_id',
            'characteristic_value_id'
        )
            ->withPivot('characteristic_id') // если полезно
            ->withTimestamps();
    }
    /** Удобная проверка: акция может примениться в данный момент? */
    public function canApplyNow(?Carbon $reference = null, ?int $weekday = null, ?string $hhmmss = null): bool
    {
        $reference ??= now();

        if (!$this->is_active) return false;
        if ($this->starts_at && $reference->lt($this->starts_at)) return false;
        if ($this->ends_at && $reference->gt($this->ends_at)) return false;

        // день недели
        $weekday ??= (int) $reference->isoWeekday(); // 1..7
        if ($this->days && !in_array($weekday, $this->days, true)) {
            return false;
        }

        // время
        $hhmmss ??= $reference->format('H:i:s');
        if (!$this->matchesTimeWindow($hhmmss)) {
            return false;
        }

        return true;
    }
}
