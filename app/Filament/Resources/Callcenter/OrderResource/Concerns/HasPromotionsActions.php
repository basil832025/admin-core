<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Concerns;

use App\Models\Shop\FixedDiscount;
use App\Models\Shop\Product;
use App\Models\Shop\TimeDiscount;
use App\Services\OrderPricing;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait HasPromotionsActions
{
    public function openPromotionsAction(): Action
    {
        return Action::make('promotions')
            ->label('Акции')
            ->color('gray')
            ->icon('heroicon-m-tag')
            ->extraAttributes([
                'data-hotkey' => 'cc-promos',
                'data-hotkey-label' => 'Alt+A',
            ])
            ->slideOver()
            ->modalWidth('2xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрыть')
            ->modalContent(fn () => view('filament.callcenter.promotions-slide-over', [
                'promos' => $this->buildAvailablePromotionsPayload(),
                'selected' => $this->resolveSelectedPromotionValue(),
            ]));
    }

    public function applyPromoSelection(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            $value = 'none';
        }

        if (isset($this->record) && $this->record?->exists) {
            $order = $this->record;

            if ($value === 'none') {
                $order->adjustments()->whereIn('type', ['fixed', 'time', 'manual_percent'])->delete();
                app(OrderPricing::class)->recalc($order);
            } else {
                [$kind, $id] = explode(':', $value, 2) + [null, null];
                $id = (int) $id;
                $pricing = app(OrderPricing::class);

                if ($kind === 'time') {
                    $moment = $this->resolveTimeMomentFromRecord($order);
                    $pricing->applyTimeExclusive($order, $id > 0 ? $id : null, 'single', $moment);
                } elseif ($kind === 'fixed') {
                    $pricing->applyFixedExclusive($order, $id > 0 ? $id : null, 'single');
                } else {
                    $pricing->recalc($order);
                }
            }

            $order->refresh();
            $this->dispatch('$refresh');

            Notification::make()
                ->success()
                ->title($value === 'none' ? 'Акция снята' : 'Акция применена')
                ->send();

            return;
        }

        // Create mode: UI-only preview + remember selection for afterCreate().
        $state = $this->form->getRawState();
        if ($state instanceof \Illuminate\Contracts\Support\Arrayable) {
            $state = $state->toArray();
        }
        $state = is_array($state) ? $state : [];

        $preview = $this->previewTotalsForPromotion($state, $value);

        $state['ui_selected_promo'] = $value;
        $state['ui_promo_preview_discount'] = (float) ($preview['discount'] ?? 0);
        $state['ui_promo_preview_final'] = (float) ($preview['final'] ?? 0);
        $state['ui_promo_preview_label'] = (string) ($preview['label'] ?? '');

        $this->form->fill($state);
    }

    /**
     * @return array<int,array{value:string,label:string,description:?string,is_active:bool,discount:float,final:float}>
     */
    protected function buildAvailablePromotionsPayload(): array
    {
        $state = $this->form->getRawState();
        if ($state instanceof \Illuminate\Contracts\Support\Arrayable) {
            $state = $state->toArray();
        }
        $state = is_array($state) ? $state : [];

        $items = collect($state['items'] ?? [])->map(fn ($i) => is_object($i) ? (array) $i : (array) $i)->values();
        $productIds = $items->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

        $locale = app()->getLocale();
        $out = [];

        // Fixed discounts
        $fixed = FixedDiscount::query()->active()->forAll()->get();
        foreach ($fixed as $d) {
            $value = 'fixed:' . $d->id;
            $label = $d->getTranslation('name', $locale) ?: $d->getNameForLocale($locale);
            $p = number_format((float) ($d->percent ?? 0), 2, '.', '');
            $label = trim($label) !== '' ? ($label . ' (−' . $p . '%)') : ('Fixed (−' . $p . '%)');

            $isActive = $d->canApply(null) && $d->hasEligibleProducts($productIds);
            $preview = $this->previewTotalsForPromotion($state, $value);
            $isActive = $isActive && (float) ($preview['discount'] ?? 0) > 0;

            $out[] = [
                'value' => $value,
                'label' => $label,
                'description' => (string) ($d->getTranslation('description', $locale) ?: ($d->description[$locale] ?? $d->description[config('app.fallback_locale')] ?? null)),
                'is_active' => (bool) $isActive,
                'discount' => (float) ($preview['discount'] ?? 0),
                'final' => (float) ($preview['final'] ?? 0),
            ];
        }

        // Time discounts
        $moment = $this->resolveMomentFromStateArray($state);
        $timeDiscounts = TimeDiscount::query()
            ->where('is_active', true)
            ->activeForMoment($moment, 'Europe/Kyiv')
            ->get();

        // Load products for characteristic checks
        $products = $productIds->isNotEmpty()
            ? Product::query()->with(['categories', 'characteristicValues'])->whereIn('id', $productIds)->get()->keyBy('id')
            : collect();

        foreach ($timeDiscounts as $d) {
            $value = 'time:' . $d->id;
            $name = (string) ($d->getTranslation('name', $locale)
                ?: $d->getTranslation('name', config('app.fallback_locale'))
                ?: '');
            $p = number_format((float) ($d->percent ?? 0), 2, '.', '');
            $label = ($name ?: 'Акция') . ' (' . $p . '%)';

            $eligible = $productIds->isNotEmpty() ? $d->hasEligibleProducts($productIds) : false;
            $isActive = $eligible;
            if ($eligible) {
                // extra conditions like checkout
                $isActive = $this->checkTimeDiscountConditionsForState($d, $state, $products);
            }

            $preview = $this->previewTotalsForPromotion($state, $value);
            $isActive = $isActive && (float) ($preview['discount'] ?? 0) > 0;

            $out[] = [
                'value' => $value,
                'label' => $label,
                'description' => (string) ($d->getTranslation('description', $locale) ?: null),
                'is_active' => (bool) $isActive,
                'discount' => (float) ($preview['discount'] ?? 0),
                'final' => (float) ($preview['final'] ?? 0),
            ];
        }

        return $out;
    }

    protected function resolveSelectedPromotionValue(): string
    {
        if (isset($this->record) && $this->record?->exists) {
            $this->record->loadMissing('adjustments');
            $adjTime = $this->record->adjustments->firstWhere('type', 'time');
            if ($adjTime) {
                $id = (int) (data_get($adjTime->meta, 'id') ?? data_get($adjTime->meta, 'time_discount_id') ?? 0);
                if ($id > 0) return 'time:' . $id;
            }
            $adjFixed = $this->record->adjustments->firstWhere('type', 'fixed');
            if ($adjFixed) {
                $id = (int) (data_get($adjFixed->meta, 'id') ?? data_get($adjFixed->meta, 'fixed_discount_id') ?? 0);
                if ($id > 0) return 'fixed:' . $id;
            }

            return 'none';
        }

        $raw = data_get($this->form->getRawState(), 'ui_selected_promo');
        $val = is_string($raw) ? trim($raw) : '';

        return $val !== '' ? $val : 'none';
    }

    /**
     * @return array{discount: float, final: float, label: string}
     */
    protected function previewTotalsForPromotion(array $state, string $value): array
    {
        $baseTotal = $this->calcBaseTotalFromState($state);
        $deliveryPrice = (float) ($state['shipping_price'] ?? 0);

        if ($value === 'none') {
            return [
                'discount' => 0.0,
                'final' => max(0.0, $baseTotal + $deliveryPrice),
                'label' => 'Без акции',
            ];
        }

        [$kind, $id] = explode(':', $value, 2) + [null, null];
        $id = (int) $id;

        $discountAmount = 0.0;
        $label = '';

        if ($kind === 'fixed' && $id > 0) {
            $fixed = FixedDiscount::query()->active()->find($id);
            if ($fixed) {
                $label = (string) $fixed->getNameForLocale();
                $discountAmount = $this->calcFixedDiscountForState($state, $fixed);
            }
        }

        if ($kind === 'time' && $id > 0) {
            $time = TimeDiscount::query()->where('is_active', true)->find($id);
            if ($time) {
                $label = (string) ($time->getTranslation('name', app()->getLocale())
                    ?: $time->getTranslation('name', config('app.fallback_locale'))
                    ?: '');
                $discountAmount = $this->calcTimeDiscountForState($state, $time);
            }
        }

        $final = max(0.0, ($baseTotal - $discountAmount) + $deliveryPrice);

        return [
            'discount' => round(max(0.0, $discountAmount), 2),
            'final' => round($final, 2),
            'label' => $label,
        ];
    }

    protected function calcBaseTotalFromState(array $state): float
    {
        $items = collect($state['items'] ?? [])->map(fn ($i) => is_object($i) ? (array) $i : (array) $i);

        return (float) $items->sum(function (array $item): float {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $mods = collect($item['modifiers'] ?? [])->map(fn ($m) => is_object($m) ? (array) $m : (array) $m);
            $modsSum = (float) $mods->sum(fn (array $m) => (float) ($m['price_modifier'] ?? 0));

            return $qty * ($price + $modsSum);
        });
    }

    protected function calcFixedDiscountForState(array $state, FixedDiscount $discount): float
    {
        $items = collect($state['items'] ?? [])->map(fn ($i) => is_object($i) ? (array) $i : (array) $i);
        $productIds = $items->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

        $products = $productIds->isNotEmpty()
            ? Product::query()->with('parent')->whereIn('id', $productIds)->get()->keyBy('id')
            : collect();

        $eligibleSum = (float) $items->sum(function (array $item) use ($products): float {
            $pid = (int) ($item['product_id'] ?? 0);
            $p = $pid > 0 ? $products->get($pid) : null;
            if (! $p || $p->excludedFromPromotions()) {
                return 0.0;
            }

            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $mods = collect($item['modifiers'] ?? [])->map(fn ($m) => is_object($m) ? (array) $m : (array) $m);
            $modsSum = (float) $mods->sum(fn (array $m) => (float) ($m['price_modifier'] ?? 0));

            return $qty * ($price + $modsSum);
        });

        return (float) $discount->calculateForTotal($eligibleSum);
    }

    protected function calcTimeDiscountForState(array $state, TimeDiscount $discount): float
    {
        $items = collect($state['items'] ?? [])->map(fn ($i) => is_object($i) ? (array) $i : (array) $i)->values();
        $productIds = $items->pluck('product_id')->filter()->map(fn ($v) => (int) $v)->unique()->values();

        if ($productIds->isEmpty()) {
            return 0.0;
        }

        $products = Product::query()->with(['categories', 'characteristicValues', 'parent'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $percent = (float) ($discount->percent ?? 0);
        $eachN = (int) ($discount->nth_item ?? 0);
        if ($percent <= 0 || $eachN < 1) {
            return 0.0;
        }

        $units = [];

        foreach ($items as $idx => $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }

            $product = $products->get($pid);
            if (! $product || $product->excludedFromPromotions()) {
                continue;
            }

            // scope checks like in callcenter
            if (! $this->matchesTimeDiscountScopeInline($discount, $product, $row)) {
                continue;
            }

            $price = (float) ($row['unit_price'] ?? 0);
            $mods = collect($row['modifiers'] ?? [])->map(fn ($m) => is_object($m) ? (array) $m : (array) $m);
            $modsSum = (float) $mods->sum(fn (array $m) => (float) ($m['price_modifier'] ?? 0));
            $unitPrice = $price + $modsSum;

            for ($i = 0; $i < $qty; $i++) {
                $units[] = [
                    'row_index' => $idx,
                    'price' => $unitPrice,
                ];
            }
        }

        if (count($units) < $eachN) {
            return 0.0;
        }

        $grouping = (string) ($discount->grouping_mode ?: TimeDiscount::GROUP_PRICE_SORTED);
        $target = (string) ($discount->apply_target ?: TimeDiscount::TARGET_CHEAPEST);
        $index = (int) ($discount->apply_index ?? 0);

        if ($grouping === TimeDiscount::GROUP_PRICE_SORTED) {
            usort($units, fn (array $a, array $b) => $b['price'] <=> $a['price']);
        }

        $chunks = array_chunk($units, $eachN);
        $chunks = array_values(array_filter($chunks, fn (array $chunk) => count($chunk) === $eachN));

        $amount = 0.0;
        foreach ($chunks as $chunk) {
            $recipient = null;
            if ($target === TimeDiscount::TARGET_MOST_EXPENSIVE) {
                $recipient = collect($chunk)->sortByDesc('price')->first();
            } elseif ($target === TimeDiscount::TARGET_INDEX) {
                $sorted = collect($chunk)->sortBy('price')->values();
                $pos = max(1, min($eachN, $index > 0 ? $index : 1));
                $recipient = $sorted->get($pos - 1);
            } else {
                $recipient = collect($chunk)->sortBy('price')->first();
            }

            if (! is_array($recipient)) {
                continue;
            }

            $amount += ((float) ($recipient['price'] ?? 0)) * ($percent / 100);
        }

        return round(max(0.0, $amount), 2);
    }

    protected function matchesTimeDiscountScopeInline(TimeDiscount $discount, Product $product, array $row): bool
    {
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

            if (! (bool) $matchProductMethod->invoke($discount, $product)) {
                return false;
            }
            if (! (bool) $matchCategoryMethod->invoke($discount, $product)) {
                return false;
            }

            $item = new \App\Models\Shop\OrderItem([
                'product_id' => $product->id,
                'qty' => (int) ($row['qty'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
            ]);
            $item->setRelation('product', $product);

            if (! (bool) $matchCharacteristicsMethod->invoke($discount, $item)) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    protected function resolveMomentFromStateArray(array $state): Carbon
    {
        $tz = config('app.timezone', 'Europe/Kyiv');
        $asap = (bool) ($state['as_soon_possible'] ?? false);

        if (! $asap) {
            $m = $this->composeMomentFromState($state['date_order'] ?? null, $state['time_order'] ?? null, $tz);
            if ($m) {
                return $m;
            }
        }

        $created = $this->composeMomentFromState($state['dat'] ?? null, $state['time_start'] ?? null, $tz);

        return $created ?? now($tz);
    }

    protected function composeMomentFromState(mixed $dateState, mixed $timeState, string $tz): ?Carbon
    {
        $date = null;
        if ($dateState instanceof \DateTimeInterface) {
            $date = Carbon::instance($dateState)->setTimezone($tz);
        } elseif (is_string($dateState) && trim($dateState) !== '') {
            try {
                $date = Carbon::parse($dateState, $tz);
            } catch (\Throwable) {
                $date = null;
            }
        }

        if (! $date) {
            return null;
        }

        $timeString = null;
        if ($timeState instanceof \DateTimeInterface) {
            $timeString = Carbon::instance($timeState)->setTimezone($tz)->format('H:i:s');
        } elseif (is_string($timeState) && trim($timeState) !== '') {
            $raw = trim($timeState);
            if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw)) {
                $timeString = strlen($raw) === 5 ? $raw . ':00' : $raw;
            } else {
                try {
                    $timeString = Carbon::parse($raw, $tz)->format('H:i:s');
                } catch (\Throwable) {
                    $timeString = null;
                }
            }
        }

        if ($timeString) {
            $date->setTimeFromTimeString($timeString);
        }

        return $date;
    }

    protected function checkTimeDiscountConditionsForState(TimeDiscount $discount, array $state, $products): bool
    {
        // channels
        $channels = $discount->channels ?? [];
        if (! empty($channels)) {
            $expected = (bool) ($state['self_pickup'] ?? false) ? 'pickup' : 'delivery';
            if (! in_array($expected, $channels, true)) {
                return false;
            }
        }

        // characteristics check already done per-row; here keep day/time window using reference moment
        $moment = $this->resolveMomentFromStateArray($state);
        $weekday = (int) $moment->isoWeekday();
        $time = $moment->format('H:i:s');

        $days = $discount->days ?? [];
        if (! empty($days) && ! in_array($weekday, $days, true)) {
            return false;
        }

        if (! $discount->matchesTimeWindow($time)) {
            return false;
        }

        return true;
    }

    protected function resolveTimeMomentFromRecord($order): Carbon
    {
        $tz = config('app.timezone', 'Europe/Kyiv');

        if (! (bool) ($order->as_soon_possible ?? false)) {
            $m = $this->composeMomentFromState($order->date_order, $order->getRawOriginal('time_order') ?? $order->time_order, $tz);
            if ($m) {
                return $m;
            }
        }

        $created = $this->composeMomentFromState($order->dat, $order->getRawOriginal('time_start') ?? $order->time_start, $tz);

        return $created ?? now($tz);
    }
}
