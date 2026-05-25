<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Models\Shop\FixedDiscount;
use App\Models\Shop\TimeDiscount;
use App\Models\Shop\PromoCode;
use App\Models\Shop\OrderItem;
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
        $isExecution = \defined(TimeDiscount::class.'::TYPE_EXECUTION')
            ? ($discount->time_type === TimeDiscount::TYPE_EXECUTION)
            : (
                (string) $discount->time_type === 'execution'
                || (int) $discount->time_type === 2
            );

        if ($isExecution) {
            // 1) если есть явные datetime поля
            $moment = $order->delivery_at ?? $order->pickup_at ?? $order->execution_at ?? null;

            // 2) если на фронте у нас дата/время отдельными полями
            if (!$moment && !empty($order->date_order) && !empty($order->time_order)) {
                // date_order: Y-m-d, time_order: H:i
                return Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $order->date_order . ' ' . $order->time_order,
                    'Europe/Kyiv'
                );
            }

            $moment = $moment ?? now('Europe/Kyiv');
        } else {
            $moment = $order->ordered_at ?? $order->created_at ?? now('Europe/Kyiv');
        }

        return $moment instanceof Carbon ? $moment : Carbon::parse($moment, 'Europe/Kyiv');
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

        if ($newAmount <= 0.0) {
            $order->adjustments()->where('type', 'time')->delete();
            $this->recalc($order);

            return;
        }

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
                'meta'          => [
                    'code' => $promo->code,
                    'discount_type' => $promo->discount_type ?? 'percent',
                    'percent' => $promo->percent ?? null,
                    'amount' => $promo->amount ?? null,
                ],
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
    public function applySelectedPromoForCheckout(Order $order, ?string $selection): void
    {
        $selection = (string) ($selection ?? 'none');

        // грузим связи, чтобы calculateAmountForOrder работал одинаково и на фронте, и в админке
        $order->loadMissing(['items.product.categories']);

        // если у Product есть attributeValues — подгрузим
        if (\method_exists(\App\Models\Shop\Product::class, 'attributeValues')) {
            $order->loadMissing(['items.product.attributeValues']);
        }

        // всегда начинаем с чистого листа
        $order->adjustments()
            ->whereIn('type', ['fixed', 'time'])
            ->delete();

        if ($selection === '' || $selection === 'none') {
            $this->recalc($order);
            return;
        }

        [$kind, $id] = \explode(':', $selection) + [null, null];
        $id = (int) $id;

        if ($kind === 'fixed') {
            // single = строго выбранная (без “max”)
            $this->applyFixedExclusive($order, $id, 'single');
            return;
        }

        if ($kind === 'time') {
            // applyTimeExclusive сам проверит activeForMoment по корректному моменту (resolveMomentFor)
            $this->applyTimeExclusive($order, $id, 'single');
            return;
        }

        // неизвестный формат — просто пересчёт без скидок
        $this->recalc($order);
    }

    /* ====================== ПЕРЕСЧЁТ ====================== */

    public function recalc(Order $order): void
    {
        $order->loadMissing(['items.product.parent', 'items.product.categories', 'items.product.characteristicValues', 'adjustments']);

        $manualOverrideItemIds = $order->adjustments
            ->where('type', 'manual_item_override')
            ->pluck('shop_order_item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($timeAdj = $order->adjustments->firstWhere('type', 'time')) {
            $timeId = (int) ($timeAdj->meta['id'] ?? $timeAdj->meta['time_discount_id'] ?? 0);
            $time = $timeId > 0 ? TimeDiscount::query()->find($timeId) : null;
            $baseAmount = $time ? abs((float) $time->calculateAmountForOrder($order)) : abs((float) $timeAdj->amount);
            $map = $time ? $this->buildTimeDiscountRecipientsMap($order, $time) : [];
            $reduction = 0.0;

            foreach ($manualOverrideItemIds as $itemId) {
                $reduction += (float) data_get($map, $itemId . '.amount', 0);
            }

            $effectiveAmount = -1 * max(0, round($baseAmount - $reduction, 2));

            if ((float) $timeAdj->amount !== (float) $effectiveAmount) {
                $timeAdj->amount = $effectiveAmount;
                $timeAdj->save();
            }
        }

        if ($fixedAdj = $order->adjustments->firstWhere('type', 'fixed')) {
            $fixedId = (int) ($fixedAdj->meta['id'] ?? $fixedAdj->meta['fixed_discount_id'] ?? 0);
            $fixed = $fixedId > 0 ? FixedDiscount::active()->find($fixedId) : null;
            $baseAmount = $fixed ? abs((float) $fixed->calculateAmountForOrder($order)) : abs((float) $fixedAdj->amount);
            $map = $fixed ? $this->buildFixedDiscountRecipientsMap($order, $fixed) : [];
            $reduction = 0.0;

            foreach ($manualOverrideItemIds as $itemId) {
                $reduction += (float) ($map[$itemId] ?? 0);
            }

            $effectiveAmount = -1 * max(0, round($baseAmount - $reduction, 2));

            if ((float) $fixedAdj->amount !== (float) $effectiveAmount) {
                $fixedAdj->amount = $effectiveAmount;
                $fixedAdj->save();
            }
        }

        foreach ($order->items as $item) {
            $item->subtotal = $item->unit_price * $item->qty;

            $baseItemAdj = $order->adjustments()
                ->where('shop_order_item_id', $item->id)
                ->where('type', '!=', 'manual_item_override')
                ->sum('amount');

            $manualItemOverride = $order->adjustments()
                ->where('shop_order_item_id', $item->id)
                ->where('type', 'manual_item_override')
                ->latest('id')
                ->first();

            $itemAdj = (float) $baseItemAdj;

            if ($manualItemOverride) {
                $overrideTarget = (float) ($manualItemOverride->meta['amount'] ?? abs((float) $manualItemOverride->amount));
                $overrideAmount = -1 * min($overrideTarget, max(0, (float) $item->subtotal));

                if ((float) $manualItemOverride->amount !== (float) $overrideAmount) {
                    $manualItemOverride->amount = $overrideAmount;
                    $manualItemOverride->meta = array_merge((array) ($manualItemOverride->meta ?? []), [
                        'amount' => abs($overrideAmount),
                    ]);
                    $manualItemOverride->save();
                }

                $itemAdj = $overrideAmount;
            }

            $item->discount_total = $itemAdj; // <= 0
            $item->tax_total      = $item->tax_total ?? 0;
            $item->total          = $item->subtotal + $item->discount_total + $item->tax_total;

            $item->unit_price_effective = $item->qty > 0
                ? round($item->total / $item->qty, 2)
                : $item->unit_price;

            $item->save();
        }

        $subtotal = (float) $order->items->sum('subtotal');

        $subtotalEligible = (float) $order->items
            ->filter(function ($item) {
                $p = $item->product;
                if (! $p) return false;
                return ! $p->excludedFromPromotions();
            })
            ->sum('subtotal');

        $otherOrderAdj = (float) $order->adjustments()
            ->whereNull('shop_order_item_id')
            ->whereNotIn('type', ['manual_percent', 'manual_fixed'])
            ->sum('amount');

        $itemsAdj = (float) $order->items->sum('discount_total');

        $manualPercent = $order->adjustments()->where('type', 'manual_percent')->first();
        $manualPercentAmount = 0.0;
        if ($manualPercent) {
            $p = (float) ($manualPercent->meta['percent'] ?? 0);
            if ($p > 0) {
                $manualPercentAmount = round(-1 * $subtotalEligible * ($p / 100), 2);
            }
            if ((float) $manualPercent->amount !== (float) $manualPercentAmount) {
                $manualPercent->amount = $manualPercentAmount;
                $manualPercent->save();
            }
        }

        $itemsAdjEligible = (float) $order->items
            ->filter(function ($item) {
                $p = $item->product;
                if (! $p) return false;
                return ! $p->excludedFromPromotions();
            })
            ->sum('discount_total');

        // Note: otherOrderAdj is expected to already be calculated against eligible items
        // (fixed/time/coupon calculation logic filters excluded products).
        $interimEligible = $subtotalEligible + $itemsAdjEligible + $otherOrderAdj + $manualPercentAmount;

        $manualFixed = $order->adjustments()->where('type', 'manual_fixed')->first();
        $manualFixedAmount = 0.0;
        if ($manualFixed) {
            $want = (float) ($manualFixed->meta['amount'] ?? 0);
            if ($want > 0) {
                $manualFixedAmount = -1 * min($want, max(0, $interimEligible));
            }
            if ((float) $manualFixed->amount !== (float) $manualFixedAmount) {
                $manualFixed->amount = $manualFixedAmount;
                $manualFixed->save();
            }
        }

        $order->subtotal       = $subtotal;
        $order->discount_total = $itemsAdj + $otherOrderAdj + $manualPercentAmount + $manualFixedAmount;
        $order->shipping_total = (bool) ($order->self_pickup ?? false)
            ? 0
            : max(0, (float) ($order->shipping_price ?? 0));
        $order->tax_total      = $order->tax_total ?? 0;

        $order->grand_total = $order->subtotal
            + $order->discount_total
            + $order->shipping_total
            + $order->tax_total;

        $order->save();
    }

    /**
     * @return array<int,array{units:int,amount:float}>
     */
    private function buildTimeDiscountRecipientsMap(Order $order, TimeDiscount $discount): array
    {
        $percent = (float) ($discount->percent ?? 0);
        $eachN = (int) ($discount->nth_item ?? 0);

        if ($percent <= 0 || $eachN < 1) {
            return [];
        }

        $units = [];

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || $product->excludedFromPromotions()) {
                continue;
            }

            if (! $this->matchesTimeDiscountScope($discount, $item)) {
                continue;
            }

            $qty = (int) ($item->qty ?? 0);
            $price = (float) ($item->unit_price ?? 0);

            if ($qty <= 0 || $price <= 0) {
                continue;
            }

            for ($i = 0; $i < $qty; $i++) {
                $units[] = ['item_id' => (int) $item->id, 'price' => $price];
            }
        }

        if ($units === []) {
            return [];
        }

        $result = [];

        if ($eachN === 1) {
            foreach ($units as $unit) {
                $itemId = (int) $unit['item_id'];
                $result[$itemId] ??= ['units' => 0, 'amount' => 0.0];
                $result[$itemId]['units'] += 1;
                $result[$itemId]['amount'] += ((float) $unit['price']) * ($percent / 100);
            }

            foreach ($result as &$row) {
                $row['amount'] = round((float) $row['amount'], 2);
            }

            return $result;
        }

        if (count($units) < $eachN) {
            return [];
        }

        $grouping = (string) ($discount->grouping_mode ?: TimeDiscount::GROUP_PRICE_SORTED);
        $target = (string) ($discount->apply_target ?: TimeDiscount::TARGET_CHEAPEST);
        $index = (int) ($discount->apply_index ?? 0);

        if ($grouping === TimeDiscount::GROUP_PRICE_SORTED) {
            usort($units, fn (array $a, array $b) => $b['price'] <=> $a['price']);
        }

        $chunks = array_chunk($units, $eachN);
        $chunks = array_values(array_filter($chunks, fn (array $chunk) => count($chunk) === $eachN));

        foreach ($chunks as $chunk) {
            if ($target === TimeDiscount::TARGET_MOST_EXPENSIVE) {
                usort($chunk, fn (array $a, array $b) => $b['price'] <=> $a['price']);
                $recipient = $chunk[0] ?? null;
            } elseif ($target === TimeDiscount::TARGET_INDEX) {
                usort($chunk, fn (array $a, array $b) => $a['price'] <=> $b['price']);
                $recipient = $chunk[max(0, min(count($chunk) - 1, ($index > 0 ? $index : 1) - 1))] ?? null;
            } else {
                usort($chunk, fn (array $a, array $b) => $a['price'] <=> $b['price']);
                $recipient = $chunk[0] ?? null;
            }

            if (! is_array($recipient)) {
                continue;
            }

            $itemId = (int) ($recipient['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $result[$itemId] ??= ['units' => 0, 'amount' => 0.0];
            $result[$itemId]['units'] += 1;
            $result[$itemId]['amount'] += ((float) ($recipient['price'] ?? 0)) * ($percent / 100);
        }

        foreach ($result as &$row) {
            $row['amount'] = round((float) $row['amount'], 2);
        }

        return $result;
    }

    /**
     * @return array<int,float>
     */
    private function buildFixedDiscountRecipientsMap(Order $order, FixedDiscount $discount): array
    {
        $items = $order->items
            ->filter(fn (OrderItem $item) => $item->product && ! $item->product->excludedFromPromotions() && (float) $item->unit_price > 0 && (int) $item->qty > 0)
            ->values();

        if ($items->isEmpty()) {
            return [];
        }

        $sum = (float) $items->sum(fn (OrderItem $item) => (float) $item->unit_price * (int) $item->qty);

        if ($sum <= 0) {
            return [];
        }

        $map = [];
        $percent = (float) ($discount->percent ?? 0);

        if ($percent > 0) {
            foreach ($items as $item) {
                $amount = round(((float) $item->unit_price * (int) $item->qty) * ($percent / 100), 2);
                if ($amount > 0) {
                    $map[$item->id] = $amount;
                }
            }

            return $map;
        }

        $fixedAmount = min((float) ($discount->amount ?? 0), $sum);

        if ($fixedAmount <= 0) {
            return [];
        }

        $remaining = round($fixedAmount, 2);
        $lastId = (int) $items->last()->id;

        foreach ($items as $item) {
            $itemSubtotal = (float) $item->unit_price * (int) $item->qty;
            $amount = ((int) $item->id === $lastId)
                ? $remaining
                : round($fixedAmount * ($itemSubtotal / $sum), 2);

            $amount = min($amount, $remaining, $itemSubtotal);
            $remaining = round($remaining - $amount, 2);

            if ($amount > 0) {
                $map[$item->id] = $amount;
            }
        }

        return $map;
    }

    private function matchesTimeDiscountScope(TimeDiscount $discount, OrderItem $item): bool
    {
        $product = $item->product;

        if (! $product) {
            return false;
        }

        try {
            static $matchProductMethod;
            static $matchCategoryMethod;
            static $matchCharacteristicsMethod;

            if (! $matchProductMethod) {
                $matchProductMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesProduct');
                $matchProductMethod->setAccessible(true);
            }

            if (! $matchCategoryMethod) {
                $matchCategoryMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesCategory');
                $matchCategoryMethod->setAccessible(true);
            }

            if (! $matchCharacteristicsMethod) {
                $matchCharacteristicsMethod = new \ReflectionMethod(TimeDiscount::class, 'matchesCharacteristics');
                $matchCharacteristicsMethod->setAccessible(true);
            }

            return (bool) $matchProductMethod->invoke($discount, $product)
                && (bool) $matchCategoryMethod->invoke($discount, $product)
                && (bool) $matchCharacteristicsMethod->invoke($discount, $item);
        } catch (\Throwable) {
            return false;
        }
    }
}
