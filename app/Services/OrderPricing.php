<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Models\Shop\FixedDiscount;
use App\Models\Shop\TimeDiscount;
use App\Models\Shop\PromoCode;
use Carbon\Carbon;

class OrderPricing
{
    /* ====================== РУЧНЫЕ СКИДКИ ====================== */

    // Ручная % — хранит только percent в meta; сумма считается в recalc()
    public function applyManualPercent(Order $order, float $percent): void
    {
        $percent = max(0, $percent);

        $adj = $order->adjustments()->firstOrNew([
            'type'               => 'manual_percent',
            'shop_order_item_id' => null,
        ]);

        $adj->label = 'Ручна знижка, %';
        $meta = $adj->meta ?? [];
        $meta['percent'] = $percent;
        $adj->meta = $meta;
        $adj->amount = 0; // пересчитывается в recalc()
        $adj->save();
    }

    // Ручная фикс (грн) — хранит желаемую сумму в meta['amount']; итог считается в recalc()
    public function applyManualFixed(Order $order, float $amountUah): void
    {
        $amountUah = max(0, $amountUah);

        $adj = $order->adjustments()->firstOrNew([
            'type'               => 'manual_fixed',
            'shop_order_item_id' => null,
        ]);

        $adj->label = 'Ручна знижка, грн';
        $meta = $adj->meta ?? [];
        $meta['amount'] = $amountUah; // положительное желание
        $adj->meta = $meta;
        $adj->amount = 0; // пересчитывается в recalc()
        $adj->save();
    }

    /* ============ ПРОГРАММНЫЕ СКИДКИ: EXCLUSIVE (max|single) ============ */
    /** Вычисляем момент времени, по которому надо проверять time-скидку */
    private function resolveMomentFor(Order $order, TimeDiscount $discount): Carbon
    {
        // Универсальная проверка: если константы нет — падаем на строковое/числовое значение
        $isExecution = \defined(TimeDiscount::class.'::TYPE_EXECUTION')
            ? ($discount->time_type === TimeDiscount::TYPE_EXECUTION)
            : (
                (string) $discount->time_type === 'execution'
                || (int) $discount->time_type === 2 // если вдруг хранится числом
            );

        $moment = $isExecution
            ? ($order->delivery_at ?? $order->pickup_at ?? $order->execution_at ?? now())
            : ($order->ordered_at  ?? $order->created_at ?? now());

        return $moment instanceof Carbon ? $moment : Carbon::parse($moment);
    }
    /** Выбор фиксированной, с учётом эксклюзивности */
    public function applyFixedExclusive(Order $order, ?int $fixedId, string $policy = 'max'): void
    {
        if (!$fixedId) {
            $order->adjustments()->where('type', 'fixed')->delete();
            $this->recalc($order);
            return;
        }

        $fixed = FixedDiscount::active()->find($fixedId);
        if (!$fixed) return;

        $newAmount = (float) $fixed->calculateAmountForOrder($order); // отрицательное
        $newMeta   = ['id' => $fixed->id, 'name' => $fixed->name];

        if ($policy === 'single') {
            // ❗️сносим конкурентов: time и manual_percent
            $order->adjustments()->whereIn('type', ['time', 'manual_percent'])->delete();

            $this->upsertProgramAdj($order, 'fixed', $fixed->name, $newAmount, $newMeta);
            $this->recalc($order);
            return;
        }

        // policy = max: сравним с активной time
        [$bestType, $bestLabel, $bestAmt, $bestMeta] = ['fixed', $fixed->name, $newAmount, $newMeta];

        if ($other = $order->adjustments()->where('type', 'time')->first()) {
            $otherId = (int) ($other->meta['id'] ?? 0);
            if ($otherId && ($time = TimeDiscount::active()->find($otherId))) {
                $otherAmt = (float) $time->calculateAmountForOrder($order);
                if (\abs($otherAmt) > \abs($bestAmt)) {
                    [$bestType, $bestLabel, $bestAmt, $bestMeta] =
                        ['time', $time->name, $otherAmt, ['id' => $time->id, 'name' => $time->name]];
                }
            }
        }

        $this->upsertProgramAdj($order, $bestType, $bestLabel, $bestAmt, $bestMeta);

        // ❗️эксклюзивность: оставляем только выбранный program-type и сносим manual_percent
        $order->adjustments()
            ->whereIn('type', ['fixed', 'time', 'manual_percent'])
            ->where('type', '!=', $bestType)
            ->delete();

        $this->recalc($order);
    }
    /** Ручная % с эксклюзией */
    public function applyManualPercentExclusive(Order $order, float $percent): void
    {
        $percent = max(0, $percent);

        if ($percent <= 0) {
            $order->adjustments()->where('type', 'manual_percent')->delete();
            $this->recalc($order);
            return;
        }

        // ставим ручную % и удаляем программные (fixed/time)
        $this->applyManualPercent($order, $percent);
        $order->adjustments()->whereIn('type', ['fixed', 'time'])->delete();

        $this->recalc($order);
    }
// app/Services/OrderPricing.php

    /** Выбор time-скидки, с учётом эксклюзивности и корректного момента */
    public function applyTimeExclusive(
        Order $order,
        ?int $timeId,
        string $policy = 'max',
        ?Carbon $moment = null
    ): void {
        if (!$timeId) {
            $order->adjustments()->where('type', 'time')->delete();
            $this->recalc($order);
            return;
        }

        $time = TimeDiscount::query()->find($timeId);
        if (!$time) return;

        $moment = $moment ?? $this->resolveMomentFor($order, $time);
//Проверить, существует ли в таблице time_discounts запись с ID = $time->id, которая активна для момента $moment во временной зоне Europe/Kyiv. Если да — $isActive = true, иначе false.»
        $isActive = TimeDiscount::query()
            ->whereKey($time->id)
            ->activeForMoment($moment, 'Europe/Kyiv')
            ->exists();

        if (!$isActive) {
            $order->adjustments()->where('type', 'time')->delete();
            $this->recalc($order);
            return;
        }

        $newAmount = (float) $time->calculateAmountForOrder($order);
        $newMeta   = ['id' => $time->id, 'name' => $time->name];

        if ($policy === 'single') {
            // ❗️сносим конкурентов: fixed и manual_percent
            $order->adjustments()->whereIn('type', ['fixed', 'manual_percent'])->delete();

            $this->upsertProgramAdj($order, 'time', $time->name, $newAmount, $newMeta);
            $this->recalc($order);
            return;
        }

        // policy = max: сравним с фиксированной
        [$bestType, $bestLabel, $bestAmt, $bestMeta] = ['time', $time->name, $newAmount, $newMeta];

        if ($other = $order->adjustments()->where('type', 'fixed')->first()) {
            $otherId = (int) ($other->meta['id'] ?? 0);
            if ($otherId && ($fixed = FixedDiscount::active()->find($otherId))) {
                $otherAmt = (float) $fixed->calculateAmountForOrder($order);
                if (\abs($otherAmt) > \abs($bestAmt)) {
                    [$bestType, $bestLabel, $bestAmt, $bestMeta] =
                        ['fixed', $fixed->name, $otherAmt, ['id' => $fixed->id, 'name' => $fixed->name]];
                }
            }
        }

        $this->upsertProgramAdj($order, $bestType, $bestLabel, $bestAmt, $bestMeta);

        // ❗️эксклюзивность: оставляем только выбранный program-type и сносим manual_percent
        $order->adjustments()
            ->whereIn('type', ['fixed', 'time', 'manual_percent'])
            ->where('type', '!=', $bestType)
            ->delete();

        $this->recalc($order);
    }
    private function upsertProgramAdj(Order $order, string $type, string $label, float $amount, array $meta): void
    {
        // Для скидок всегда минус
        if (in_array($type, ['fixed', 'time', 'coupon', 'manual_fixed', 'manual_percent'], true)) {
            $amount = -abs((float) $amount);
        }

        $adj = $order->adjustments()->firstOrNew([
            'type'               => $type, // 'fixed' или 'time'
            'shop_order_item_id' => null,
        ]);

        $adj->label  = $label;
        $adj->amount = round($amount, 2);
        $adj->meta   = $meta;
        $adj->save();
    }


    /* ====================== ПРОМОКОД ====================== */

    /* ====================== ПРОМОКОД ====================== */


    public function applyPromo(Order $order, string $code): bool
    {
        $code = trim($code);
        if ($code === '') return false;

        $promo = \App\Models\Shop\PromoCode::active()
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])
            ->first();

        if (! $promo) {
            $order->adjustments()->where('type', 'coupon')->delete();
            return false;
        }

        // ⚠️ если нужен контроль «1 раз на клиента» — клиент должен быть выбран
        $perClientLimit = (int) ($promo->per_client_limit ?? 0);
      //  dump($perClientLimit,$promo,$code,$order->clients_id);
        if ($perClientLimit > 0) {
            if (! $order->clients_id) {
                // Можно вернуть false, а можно бросить ошибку
                // throw ValidationException::withMessages(['ui_promo_code' => 'Спочатку оберіть клієнта.']);
                return false;
            }

            $usedCount = \App\Models\Shop\PromoCodeUsage::query()
                ->where('promo_code_id', $promo->id)
                ->where('client_id', $order->clients_id)
                ->count();

            if ($usedCount >= $perClientLimit) {
                // уже использовал этот клиент
                // throw ValidationException::withMessages(['ui_promo_code' => 'Цей промокод вже використаний цим клієнтом.']);
                return false;
            }
        }

        if (! $order->exists) $order->save();

        $amount = method_exists($promo, 'calculateAmountForOrder')
            ? (float) ($promo->calculateAmountForOrder($order) ?? 0)
            : 0.0;

        if ($amount == 0.0) {
            $order->adjustments()->where('type', 'coupon')->delete();
            $promo->unmarkUsed($order->id);
            $this->recalc($order);
            return false;
        }

        // один купон на заказ (перезапишет, если был другой код)
        $order->adjustments()->updateOrCreate(
            ['type' => 'coupon', 'shop_order_item_id' => null],
            [
                'label'         => 'Промокод ' . ($promo->name ?? $promo->code),
                'amount'        => $amount,
                'promotion_id'  => $promo->promotion_id ?? null,
                'promo_code_id' => $promo->id,
                'meta'          => ['code' => $promo->code, 'percent' => $promo->percent ?? null],
            ]
        );

        // учитываем использование (перезапишет client_id, если раньше был NULL)
        $order->refresh();
        $promo->markUsed($order->clients_id, $order->id);

        $this->recalc($order);
        return true;
    }

  /*  public function applyPromo(Order $order, string $code): bool
    {
        $code = trim($code);
        if ($code === '') return false;

        // ищем активный код без учёта регистра
        $promo = \App\Models\Shop\PromoCode::active()
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper($code)])
            ->first();

        if (! $promo) {
            // если код не найден — убираем следы
            $order->adjustments()->where('type', 'coupon')->delete();
            // если надо — удаляем usage старого кода (если ты его хранишь в adj.meta)
            return false;
        }

        // На всякий случай, чтобы был order_id
        if (! $order->exists) $order->save();

        // сумма по твоей модели
        $amount = method_exists($promo, 'calculateAmountForOrder')
            ? (float) ($promo->calculateAmountForOrder($order) ?? 0)
            : 0.0;

        if ($amount == 0.0) {
            // убираем корректировку и usage
            $order->adjustments()->where('type', 'coupon')->delete();
            $promo->unmarkUsed($order->id);
            $this->recalc($order);
            return false;
        }

        // один купон на заказ
        $order->adjustments()->updateOrCreate(
            ['type' => 'coupon', 'shop_order_item_id' => null],
            [
                'label'         => 'Промокод ' . ($promo->name ?? $promo->code),
                'amount'        => $amount, // отрицательное
                'promotion_id'  => $promo->promotion_id ?? null,
                'promo_code_id' => $promo->id,
                'meta'          => ['code' => $promo->code, 'percent' => $promo->percent ?? null],
            ]
        );

        // 🔴 ВАЖНО: учёт использования (идемпотентно)
        $promo->markUsed($order->clients_id, $order->id);

        $this->recalc($order);
        return true;
    }*/

  /*  public function applyPromo(Order $order, string $code): void
    {
        $code = trim($code);

        // ищем активный промокод (если нужен поиск без регистра — раскомментируй whereRaw)
        $promo = PromoCode::active()
            // ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->where('code', $code)
            ->first();

        if (! $promo) {
            // можно ещё удалить старые coupon-аджастменты, если код не найден
            return;
        }

        // Если в модели есть свой расчёт — используем его, иначе считаем внутри сервиса
        $amount = method_exists($promo, 'calculateAmountForOrder')
            ? (float) ($promo->calculateAmountForOrder($order) ?? 0)
            : (float) ($this->calcPromoAmount($order, $promo) ?? 0);

        if ($amount === 0.0) {
            // при нулевой сумме — не создаём запись (или можно удалить существующую)
            return;
        }

        // снимаем предыдущие coupon-аджастменты, чтобы не плодить дубликаты
        $order->adjustments()->where('type', 'coupon')->delete();

        $order->adjustments()->create([
            'type'               => 'coupon',
            'shop_order_item_id' => null,
            'label'              => 'Промокод ' . ($promo->name ?? $promo->code),
            'amount'             => $amount,     // ОТРИЦАТЕЛЬНОЕ число
            'promotion_id'       => $promo->promotion_id ?? null,
            'promo_code_id'      => $promo->id,
            'meta'               => [
                'code'    => $promo->code,
                'percent' => $promo->percent ?? $promo->discount_percent ?? null,
            ],
        ]);

        $this->recalc($order);
    }*/

    /**
     * Бэкап-расчёт суммы промокода: процент от базы.
     * Если нет сложных условий — применяем к сумме всех позиций заказа.
     * Возвращаем ОТРИЦАТЕЛЬНОЕ число.
     */
    private function calcPromoAmount(Order $order, PromoCode $promo): float
    {
        // процент может храниться в разных полях — пробуем оба
        $percent = (float) ($promo->percent ?? $promo->discount_percent ?? 0);
        if ($percent <= 0) {
            return 0.0;
        }

        // базу считаем как сумму субтоталов по позициям (без других скидок)
        $eligibleSubtotal = (float) $order->items->sum(fn ($i) => (float) $i->unit_price * (int) $i->qty);

        // TODO: если у промокода есть фильтры (товары/категории/характеристики),
        // отфильтруй здесь $order->items и посчитай subtotal только по "подходящим".

        $amount = $eligibleSubtotal * ($percent / 100);

        return $amount > 0 ? -round($amount, 2) : 0.0;
    }
    /* ====================== ПЕРЕСЧЁТ ====================== */

    /* ====================== ПЕРЕСЧЁТ ====================== */

    public function recalc(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->subtotal = $item->unit_price * $item->qty;

            $itemAdj = $order->adjustments()
                ->where('shop_order_item_id', $item->id)
                ->sum('amount');

            $item->discount_total = $itemAdj; // <= 0
            $item->tax_total      = $item->tax_total ?? 0;
            $item->total          = $item->subtotal + $item->discount_total + $item->tax_total;

            $item->unit_price_effective = $item->qty > 0
                ? round($item->total / $item->qty, 2)
                : $item->unit_price;

            $item->save();
        }

        $subtotal = (float) $order->items->sum('subtotal');

        $otherOrderAdj = (float) $order->adjustments()
            ->whereNull('shop_order_item_id')
            ->whereNotIn('type', ['manual_percent', 'manual_fixed'])
            ->sum('amount');

        $itemsAdj = (float) $order->items->sum('discount_total');

        $manualPercent = $order->adjustments()->where('type', 'manual_percent')->first();
        $manualPercentAmount = 0.0;
        if ($manualPercent) {
            $p = (float) ($manualPercent->meta['percent'] ?? 0);
            if ($p > 0) $manualPercentAmount = round(-1 * $subtotal * ($p / 100), 2);
            if ((float) $manualPercent->amount !== (float) $manualPercentAmount) {
                $manualPercent->amount = $manualPercentAmount;
                $manualPercent->save();
            }
        }

        $interim = $subtotal + $itemsAdj + $otherOrderAdj + $manualPercentAmount;

        $manualFixed = $order->adjustments()->where('type', 'manual_fixed')->first();
        $manualFixedAmount = 0.0;
        if ($manualFixed) {
            $want = (float) ($manualFixed->meta['amount'] ?? 0);
            if ($want > 0) $manualFixedAmount = -1 * min($want, max(0, $interim));
            if ((float) $manualFixed->amount !== (float) $manualFixedAmount) {
                $manualFixed->amount = $manualFixedAmount;
                $manualFixed->save();
            }
        }

        $order->subtotal       = $subtotal;
        $order->discount_total = $itemsAdj + $otherOrderAdj + $manualPercentAmount + $manualFixedAmount;
        $order->shipping_total = $order->shipping_total ?? 0;
        $order->tax_total      = $order->tax_total ?? 0;

        $order->grand_total = $order->subtotal
            + $order->discount_total
            + $order->shipping_total
            + $order->tax_total;

        $order->save();
    }
}
