<?php
// app/Models/Shop/TimeDiscount.php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Spatie\Translatable\HasTranslations;

class TimeDiscount extends Model
{
    use SoftDeletes;
    use HasTranslations;
    public const TYPE_ORDER     = 'order';      // по времени заказа
    public const TYPE_EXECUTION = 'execution';  // по времени выполнения (доставка/выдача)
    protected $table = 'bs_shop_time_discounts';

    protected $fillable = [
        'name',
        'description',
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
    public $translatable = [
        'name', 'description',
    ];
    protected $casts = [
        'days'      => 'array',     // JSON <-> array
        'percent'   => 'decimal:2',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'description' => 'array',
    ];

    /** Скоуп активных по флагу и датам проверяет, активна ли скидка сейчас (флаг is_active, дата начала/окончания) . */
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
    /**
     * Сколько скидки (положительное число) дать с указанной суммы.
     * Упрощённая версия для живого пересчёта на checkout.
     */
    public function calculateForTotal(float $sum): float
    {
        if ($sum <= 0) {
            return 0.0;
        }

        $percent = (float) ($this->percent ?? 0);
        if ($percent > 0) {
            return round($sum * $percent / 100, 2);
        }

        $amount = (float) ($this->amount ?? 0);
        if ($amount > 0) {
            return round(min($amount, $sum), 2);
        }

        return 0.0;
    }

    /**
     * Есть ли в корзине хоть один товар, на который реально распространяется акция
     * (по группам/товарам). Характеристики при желании можно тоже учесть.
     */
    public function hasEligibleProducts(Collection $productIds): bool
    {
        $ids = $productIds->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return false;
        }

        $hasCats  = $this->categories()->exists();
        $hasProds = $this->products()->exists();

        // если не задано ни категорий, ни товаров — значит акция без ограничений по ассортименту
        if (! $hasCats && ! $hasProds) {
            return true;
        }

        // матч по конкретным товарам
        if ($hasProds && $this->products()
                ->whereIn('bs_shop_products.id', $ids)
                ->exists()
        ) {
            return true;
        }

        // матч по категориям (через товары категории)
        if ($hasCats && $this->categories()
                ->whereHas('products', function ($q) use ($ids) {
                    // пусть Eloquent сам подставит нужную таблицу
                    $table = $q->getModel()->getTable();   // например, 'bs_products'
                    $q->whereIn($table . '.id', $ids);
                    // либо даже просто: $q->whereIn('id', $ids);
                })
                ->exists()
        ) {
            return true;
        }


        return false;
    }
    /*проверяет, действует ли скидка в конкретный момент времени и учитывает:
день недели (days или days_mask),
временное окно (time_from, time_to или window_start, window_end),
пересечение через полночь (например, 22:00–06:00) .
Таким образом, скидка может быть ограничена днями недели и временем суток (happy hours).*/
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
        /*Берёт процент скидки percent.Если 0 или меньше — скидка не применяется .*/
        $percent = (float) ($this->percent ?? 0);
        if ($percent <= 0) return 0.0;
        //Берёт параметр each_n. Это "каждый N-й товар", для которого применяется скидка (например, «каждая 2-я пицца со скидкой») .
        $eachN = (int) ($this->nth_item ?? 1);
      //  if ($eachN <= 1) $eachN = 2; // «кожна друга» по умолчанию, при желании оставь 1

        // 1) Собираем eligible позиции (примени свои фильтры при необходимости)
        $eligibleRows = $order->items->filter(function ($row) {
            $product = $row->product;
            if (! $product) return false;

            // какие группы вообще заданы у скидки
            $hasCats  = ! empty($this->selectedCategoryIds());
            $hasProds = ! empty($this->selectedProductIds());
            $hasChars = ! empty($this->selectedCharacteristicValueIds());

            // матчи по группам (считаем только если группа задана)
            $catMatch  = $hasCats  ? $this->matchesCategory($product)    : null;
            $prodMatch = $hasProds ? $this->matchesProduct($product)     : null;
            $charMatch = $hasChars ? $this->matchesCharacteristics($row) : null;
     //       dd($hasCats,$hasProds,$hasChars,$catMatch,$prodMatch,$charMatch,$product);
            // 1) база ассортимента: категории ИЛИ товары (если хотя бы что-то из этого задано)
            if ($hasCats || $hasProds) {
                $baseOk = (($hasCats  && $catMatch) || ($hasProds && $prodMatch));
                if (! $baseOk) return false;
            }

            // 2) характеристики — всегда сужают
            if ($hasChars && ! $charMatch) {
                return false;
            }

            // 3) если ни одного фильтра не задано — значит скидка без ограничений (всё подходит)
            return true;
        });
     //   dd($eligibleRows);
        if ($eligibleRows->isEmpty()) {
            return 0.0;
        }
    //    dd($order->items,$eligibleRows);
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
    //    dd($count,$eachN,$chunks);
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
    // === КАТЕГОРИИ ===
// Вариант A: уже есть pivot-связь categories() (shop_time_discount_categories) — используем её
    protected function selectedCategoryIds(): array
    {
        static $cache;
        if ($cache !== null) return $cache;

        try {
            $ids = $this->categories()->pluck('bs_product_categories.id')->all(); // или ->pluck('id')
        } catch (\Throwable $e) {
            $ids = [];
        }
        return $cache = array_map('intval', $ids);
    }

    protected function matchesCategory($product): bool
    {
        $ids = $this->selectedCategoryIds();

        if (empty($ids)) {
            // если категории не заданы — не ограничиваем по категориям
            return true;
        }
        $catId = (int) $product->category_id;
      //  dd($ids,property_exists($product, 'category_id'),$product->category_id,in_array((int)$product->category_id, $ids, true));
        // a) если есть mainCategory_id
        if ($product->category_id) {
           // dd(in_array((int)$product->category_id, $ids, true));
            return in_array((int)$product->category_id, $ids, true);
        }

        // b) если у товара есть связь categories()
        if (method_exists($product, 'categories')) {
            $pids = $product->categories()->pluck('bs_product_categories.id')->all();
            return (bool) array_intersect($ids, array_map('intval', $pids));
        }

        return false;
    }

// === ПРОДУКТЫ ===
// Вариант A: pivot-связь (создай при необходимости)
    public function products()
    {
        // таблицу зови как у тебя реально: shop_time_discount_products (time_discount_id, product_id)
        return $this->belongsToMany(
            \App\Models\Shop\Product::class,
            'bs_shop_time_discount_products',
            'time_discount_id',
            'product_id'
        )->withTimestamps();
    }

    protected function selectedProductIds(): array
    {
        static $cache;
        if ($cache !== null) return $cache;

        // Вариант A: через pivot
        try {
            $ids = $this->products()->pluck('products.id')->all();
        } catch (\Throwable $e) {
            $ids = [];
        }

        // Вариант B (если используешь JSON колонку product_ids): раскомментируй
        // $ids = array_map('intval', (array) ($this->product_ids ?? []));

        return $cache = array_map('intval', $ids);
    }

    protected function matchesProduct($product): bool
    {
        $ids = $this->selectedProductIds();
        if (empty($ids)) {
            // если список товаров не задан — не ограничиваем по товарам
            return true;
        }
        return in_array((int)$product->id, $ids, true);
    }

// === ЗНАЧЕНИЯ ХАРАКТЕРИСТИК ===
// Вариант A: pivot-связь со значениями характеристик
    public function characteristicValues()
    {
        // таблицу зови как у тебя: shop_time_discount_characteristic_values (time_discount_id, characteristic_value_id)
        return $this->belongsToMany(
            \App\Models\Shop\CharacteristicValue::class,
            'bs_shop_time_discount_characteristic_values',
            'time_discount_id',
            'characteristic_value_id'
        )->withTimestamps();
    }

    protected function selectedCharacteristicValueIds(): array
    {
        static $cache;
        if ($cache !== null) return $cache;

        // Вариант A: через pivot
    //    dd($this->characteristicValues()->pluck('shop_time_discount_characteristic_values.id')->all());
        try {
            $ids = $this->characteristicValues()->pluck('shop_time_discount_characteristic_values.characteristic_value_id')->all();
         //   dd($ids);
        } catch (\Throwable $e) {
            $ids = [];
        }

        // Вариант B (если используешь JSON колонку char_value_ids): раскомментируй
        // $ids = array_map('intval', (array) ($this->char_value_ids ?? []));

        return $cache = array_map('intval', $ids);
    }

    /**
     * Проверяем, участвует ли строка заказа по характеристикам.
     * Предположение: в OrderItem где-то хранится выбор значений (например, в $row->meta['char_value_ids'])
     * или есть связь $row->characteristicValues().
     */
    protected function matchesCharacteristics($orderItem): bool
    {
        $needIds = array_map('intval', (array) $this->selectedCharacteristicValueIds());

        // если в скидке нет фильтра по значениям — не ограничиваем
        if (empty($needIds)) {
            return true;
        }

        $product = $orderItem->product;
        if (! $product) {
            return false;
        }

        // вытягиваем value_id с товара
        $selected = $this->productCharacteristicValueIds($product);
       // dd($selected);
        // если у товара нет таких значений — не подходит
        if (empty($selected)) {
            return false;
        }
      //  dd($needIds,$selected,array_intersect($needIds, $selected));
        // требуется хотя бы одно пересечение
        return (bool) array_intersect($needIds, $selected);
    }

    /**
     * Возвращает массив characteristic_value_id, привязанных к товару.
     * Пытается через связь product->characteristicValues(), иначе — прямым запросом к pivot.
     */
    protected function productCharacteristicValueIds($product): array
    {
      //  dd($product);


        // Вариант 1: у товара есть связь characteristicValues()
     /*   if (method_exists($product, 'characteristicValues')) {
         //   dd('1tyt',$product,$product->characteristicValues()->pluck('characteristic_values.id')->all());
            try {

                return array_map('intval', $product->characteristicValues()->pluck('characteristic_values.id')->all());
            } catch (\Throwable $e) {
                // пойдём вторым путём
            }
        }*/
        /*    dd('qtqt',\DB::table('product_characteristic_value')
                ->where('product_id', $product->id)
                ->whereNotNull('characteristic_value_id')
                ->pluck('characteristic_value_id')
                ->map(fn ($v) => (int) $v)
                ->all());*/
        // Вариант 2: напрямую из таблицы значений товара (подставь своё точное имя)
        // Часто встречается: product_characteristic_values (product_id, characteristic_id, characteristic_value_id, ...)
        return \DB::table('product_characteristic_value')
            ->where('product_id', $product->id)
            ->whereNotNull('characteristic_value_id')
            ->pluck('characteristic_value_id')
            ->map(fn ($v) => (int) $v)
            ->all();
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
            'bs_shop_time_discount_categories',
            'time_discount_id',
            'category_id'
        )->withTimestamps();
    }



    /** belongsToMany: характеристики */
    public function characteristics()
    {
        return $this->belongsToMany(
            \App\Models\Shop\Characteristic::class,
            'bs_shop_time_discount_characteristics',
            'time_discount_id',
            'characteristic_id'
        )->withTimestamps();
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
