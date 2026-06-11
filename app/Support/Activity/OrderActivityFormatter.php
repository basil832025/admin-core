<?php

namespace App\Support\Activity;
use App\Enums\OrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class OrderActivityFormatter
{
    /* ===== публичные ===== */

    public static function summary(Activity|array|string|Collection $activity): string
    {
        return self::text($activity) ?? '—';
    }

    public static function tooltip(Activity|array|string|Collection $activity): string
    {
        $p = $activity instanceof Activity ? $activity->properties : $activity;

        if ($p instanceof Collection) $p = $p->toArray();
        if (is_array($p)) {
            return json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return is_string($p) ? $p : '';
    }
// Возвращает русскую метку статуса через enum, если код известен
    private static function statusLabel(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        // tryFrom вернёт null для неизвестного кода, тогда покажем код как есть
        return OrderStatus::tryFrom($code)?->getLabel() ?? $code;
    }

    public static function text(Activity|array|string|Collection $activity): ?string
    {
        $p = $activity instanceof Activity ? $activity->properties : $activity;
        $p = self::toArray($p);
        if ($p === null) return null;

        $action = Arr::get($p, 'action');

        // 1) адрес
        if ($action === 'address_changed' || Arr::hasAny($p, ['address_from', 'address_to'])) {
            return self::formatAddressChanged($p);
        }

        // 2) смена клиента (идентификатор или имя)
        if (self::fieldChanged($p, 'clients_id') || Arr::hasAny($p, ['client_from', 'client_to'])) {
            return self::formatClientChanged($p);
        }

        // 3) товары
        if (in_array($action, ['item_created','item_updated','item_deleted'], true)) {
            return self::formatOrderItem($p, $action);
        }

        // 4) модификаторы (вариации/характеристики)
        // 2) Модификаторы (вариации/характеристики)
        $action = Arr::get($p, 'action');
        if (in_array($action, ['modifier_created', 'modifier_updated', 'modifier_deleted'], true)) {
            return self::formatModifierLine($p);   // ← одна функция для всех трёх
        }
        // 2) откат статуса (или просто смена статуса с причиной)
        if (
            $action === 'status_downgraded'
            || Arr::hasAny($p, ['from', 'to', 'reason'])
        ) {
            return self::formatStatusChanged($p);
        }
       /* if (
            in_array($action, ['modifier_created','modifier_updated','modifier_deleted'], true)
            || Arr::has($p, 'modifier') // ← если action пуст, но модификатор есть
        ) {
            $inferred = $action ?: (
            Arr::has($p, 'snapshot') ? 'modifier_deleted' :
                (Arr::has($p, 'old') || Arr::has($p, 'attributes') ? 'modifier_updated' : 'modifier_created')
            );

            return self::formatModifier($p, $inferred);
        }*/

        // 5) статус / примечание (дефолтный формат spatie)
        if (Arr::hasAny($p, ['old','attributes'])) {
            $text = self::formatStatusAndNoteChanged($p);
            if ($text !== null) return $text;
        }

        // 6) общий «умный» фоллбек
        return self::formatGeneric($p);
    }
    private static function money($value): string
    {
        $v = is_numeric($value) ? (float) $value : (float) str_replace(',', '.', (string) $value);
        return number_format($v, 2, '.', ' ');
    }
    /* ====== helpers ====== */
// OrderActivityFormatter.php
    public static function operation(Activity $a): string
    {
        // Модификаторы
        if ($a->log_name === 'order.items') {
            $act = Arr::get($a->properties, 'action');
            if (in_array($act, ['modifier_created','modifier_updated','modifier_deleted'], true)) {
                return [
                    'modifier_created' => __('order.journal.operations.modifier_created'),
                    'modifier_updated' => __('order.journal.operations.modifier_updated'),
                    'modifier_deleted' => __('order.journal.operations.modifier_deleted'),
                ][$act];
            }
        }

        // По событию Spatie
        return [
                'created' => __('order.journal.operations.created'),
                'updated' => __('order.journal.operations.updated'),
                'deleted' => __('order.journal.operations.deleted'),
            ][$a->event] ?? ($a->description ?? '');
    }

    protected static function toArray(mixed $props): ?array
    {
        if ($props instanceof Collection) return $props->toArray();
        if (is_string($props)) {
            $d = json_decode($props, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $d : null;
        }
        return is_array($props) ? $props : null;
    }
    protected static function formatStatusChanged(array $p): string
    {
        $from   = Arr::get($p, 'from');        // код статуса: 'processing' и т.п.
        $to     = Arr::get($p, 'to');
        $reason = trim((string) Arr::get($p, 'reason', ''));

        // Человеческие подписи через enum
        $fromLabel = $from ? OrderStatus::from($from)->getLabel() : '—';
        $toLabel   = $to   ? OrderStatus::from($to)->getLabel()   : '—';

        $parts = [];
        $parts[] = __('order.journal.labels.status') . ': ' . self::arrow($fromLabel, $toLabel);

        if ($reason !== '') {
            $parts[] = __('order.journal.labels.reason') . ': ' . $reason;
        }

        return implode(' • ', $parts);
    }
    protected static function fieldChanged(array $p, string $field): bool
    {
        return Arr::has($p, "old.$field") || Arr::has($p, "attributes.$field");
    }

    protected static function v(mixed $val): string
    {
        if ($val === null || $val === '') return '—';
        if (is_bool($val)) return $val ? __('order.journal.yes') : __('order.journal.no');
        return (string) $val;
    }

    protected static function arrow(mixed $from, mixed $to): string
    {
        return self::v($from) . ' → ' . self::v($to);
    }

    /* ====== форматтеры ====== */

    protected static function formatAddressChanged(array $p): string
    {
        $fmt = function (?array $a): string {
            $a ??= [];
            $parts = array_filter([
                Arr::get($a, 'street'),
                'д.' . Arr::get($a, 'house'),
                Arr::get($a, 'apartment') ? 'кв. ' . Arr::get($a, 'apartment') : null,
                Arr::get($a, 'entrance') ? 'подъезд ' . Arr::get($a, 'entrance') : null,
                Arr::get($a, 'floor') ? 'этаж ' . Arr::get($a, 'floor') : null,
                Arr::get($a, 'intercom') ? 'домофон ' . Arr::get($a, 'intercom') : null,
            ]);
            return implode(', ', $parts);
        };

        $from = $fmt(Arr::get($p, 'address_from'));
        $to   = $fmt(Arr::get($p, 'address_to'));

        $noteFrom = Arr::get($p, 'address_from.note');
        $noteTo   = Arr::get($p, 'address_to.note');

        $out = [__('order.journal.labels.delivery_address') . ': ' . self::arrow($from ?: '—', $to ?: '—')];
        if ($noteFrom !== null || $noteTo !== null) {
            $out[] = __('order.journal.labels.note') . ': ' . self::arrow($noteFrom, $noteTo);
        }

        return implode(' • ', $out);
    }

    protected static function formatClientChanged(array $p): ?string
    {
        // через old/attributes (id)
        if (self::fieldChanged($p, 'clients_id')) {
            return __('order.journal.labels.client') . ': ' . self::arrow(
                    Arr::get($p, 'old.clients_id'),
                    Arr::get($p, 'attributes.clients_id')
                );
        }

        // через client_from / client_to (имена)
        if (Arr::hasAny($p, ['client_from','client_to'])) {
            return __('order.journal.labels.client') . ': ' . self::arrow(
                    Arr::get($p, 'client_from'),
                    Arr::get($p, 'client_to')
                );
        }

        return null;
    }

    protected static function formatStatusAndNoteChanged(array $p): ?string
    {
        $fs = Arr::get($p, 'old.status');
        $ts = Arr::get($p, 'attributes.status');
        $fromStatus = Arr::get($p, 'old.status');
        $toStatus   = Arr::get($p, 'attributes.status');
        $fn = Arr::get($p, 'old.notes');
        $tn = Arr::get($p, 'attributes.notes');

        $parts = [];
        if ($fs !== null || $ts !== null) {
            $parts[] = __('order.journal.labels.status') . ': ' . self::arrowText(
                    self::statusLabel($fromStatus),
                    self::statusLabel($toStatus),
);
        }
        if ($fn !== null || $tn !== null) {
            $parts[] = __('order.journal.labels.note') . ': ' . self::arrow($fn, $tn);
        }

        foreach ((array) Arr::get($p, 'attributes', []) as $key => $new) {
            if (in_array((string) $key, ['status', 'notes'], true)) {
                continue;
            }

            $old = Arr::get($p, "old.$key");
            if (self::shouldSkipOrderDiff((string) $key, $old, $new)) {
                continue;
            }

            $parts[] = self::formatOrderDiff((string) $key, $old, $new);
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    protected static function formatOrderItem(array $p, string $action): string
    {
        $prodName = Arr::get($p, 'product.name') ?: (__('order.journal.labels.product') . ' #' . (Arr::get($p, 'product.id') ?? '—'));
        $prefix   = __('order.journal.labels.product') . ': ' . $prodName;

        if ($action === 'item_created') {
            $qty   = Arr::get($p, 'attributes.qty');
            $price = Arr::get($p, 'attributes.unit_price');
            $out = [$prefix, __('order.journal.labels.quantity') . ': ' . self::v($qty)];
            if ($price !== null) $out[] = __('order.journal.labels.price') . ': ' . self::v($price);
            return implode(' • ', $out);
        }

        if ($action === 'item_deleted') {
            $qty   = Arr::get($p, 'snapshot.qty');
            $price = Arr::get($p, 'snapshot.unit_price');
            $out = [$prefix, __('order.journal.labels.deleted'), __('order.journal.labels.quantity') . ': ' . self::v($qty)];
            if ($price !== null) $out[] = __('order.journal.labels.price') . ': ' . self::v($price);
            return implode(' • ', $out);
        }

        // updated
        $out = [$prefix];
        foreach (['qty' => __('order.journal.labels.quantity'), 'unit_price' => __('order.journal.labels.price')] as $k => $label) {
            $old = Arr::get($p, "old.$k");
            $new = Arr::get($p, "attributes.$k");
            if ($old !== null || $new !== null) {
                $out[] = $label . ': ' . self::arrow($old, $new);
            }
        }
        return implode(' • ', $out);
    }

    protected static function formatModifier(array $p, string $action): string
    {
        $prodName  = Arr::get($p, 'product.name') ?: (__('order.journal.labels.product') . ' #' . (Arr::get($p, 'product.id') ?? '—'));
        $type      = Arr::get($p, 'modifier.type');
        $typeLabel = $type === 'variation'
            ? __('order.journal.labels.variation')
            : ($type === 'characteristic' ? __('order.journal.labels.characteristic') : __('order.journal.labels.characteristic'));

        $label    = Arr::get($p, 'modifier.value_label') ?? ('#' . Arr::get($p, 'modifier.value_id'));
        $priceMod = Arr::get($p, 'modifier.price_modifier');

        $head = __('order.journal.labels.product') . ": {$prodName} • {$typeLabel}: {$label}";

        if ($action === 'modifier_created') {
            $out = [$head];
            if ($priceMod !== null) $out[] = __('order.journal.labels.price_plus') . ': ' . self::v($priceMod);
            return implode(' • ', $out);
        }

        if ($action === 'modifier_deleted') {
            $snap = Arr::get($p, 'snapshot', []);
            $lbl  = Arr::get($snap, 'value_label', $label);
            $pm   = Arr::get($snap, 'price_modifier', $priceMod);
            $out  = [__('order.journal.labels.modifier_deleted') . " • {$typeLabel}: {$lbl}"];
            if ($pm !== null) $out[] = __('order.journal.labels.price_plus') . ': ' . self::v($pm);
            return implode(' • ', $out);
        }

        // modifier_updated
        $out = [$head];
        foreach ([
                     'value_label'    => $typeLabel,
                     'price_modifier' => __('order.journal.labels.price_plus'),
                 ] as $k => $lbl) {
            $old = Arr::get($p, "old.$k");
            $new = Arr::get($p, "attributes.$k");
            if ($old !== null || $new !== null) {
                $out[] = "{$lbl}: " . self::arrow($old, $new);
            }
        }
        return implode(' • ', $out);
    }
    /** Универсальная строка для модификаторов (создан/изменён/удалён) */
    private static function formatModifierLine(array $props): string
    {
        $productName = self::productNameFromProps($props);

        // тип может лежать как в modifier, так и в snapshot (при delete)
        $type = Arr::get($props, 'modifier.type') ?? Arr::get($props, 'snapshot.type');
        $kind = $type === 'variation' ? __('order.journal.labels.variation') : __('order.journal.labels.characteristic');

        $action = Arr::get($props, 'action');

        // «текущие» (для created/deleted)
        $curLabel = Arr::get($props, 'modifier.value_label')
            ?? Arr::get($props, 'snapshot.value_label');
        $curPrice = Arr::get($props, 'modifier.price_modifier')
            ?? Arr::get($props, 'snapshot.price_modifier');

        // «старое/новое» (для updated)
        $oldLabel = Arr::get($props, 'old.value_label');
        $newLabel = Arr::get($props, 'attributes.value_label');
        $oldPrice = Arr::get($props, 'old.price_modifier');
        $newPrice = Arr::get($props, 'attributes.price_modifier');

        $parts = [];
        if ($productName) {
            $parts[] = __('order.journal.labels.product') . ": {$productName}";
        }

        // Заголовок модификатора
        if ($action === 'modifier_updated' && ($oldLabel !== null || $newLabel !== null)) {
            $parts[] = "{$kind}: " . self::arrowText($oldLabel, $newLabel);
        } else {
            $parts[] = "{$kind}: " . ($curLabel ?: '—');
        }

        // Цена + (стрелка для updated, одно значение для created/deleted)
        if ($action === 'modifier_updated' && ($oldPrice !== null || $newPrice !== null)) {
            $parts[] = __('order.journal.labels.price_plus') . ': ' . self::arrowMoney($oldPrice, $newPrice);
        } elseif ($curPrice !== null && $curPrice !== '') {
            $parts[] = __('order.journal.labels.price_plus') . ': ' . self::money($curPrice);
        }

        return implode(' • ', array_filter($parts));
    }

    private static function arrowText($old, $new, string $dash = '—'): string
    {
        $o = $old ?? $dash;
        $n = $new ?? $dash;
        return "{$o} → {$n}";
    }
    private static function arrowMoney($old, $new): string
    {
        $o = ($old === null || $old === '') ? '—' : self::money($old);
        $n = ($new === null || $new === '') ? '—' : self::money($new);
        return "{$o} → {$n}";
    }
    /** Имя товара берём из нескольких возможных мест */
    private static function productNameFromProps(array $props): ?string
    {
        return Arr::get($props, 'product.name')      // то что мы теперь пишем при delete
            ?? Arr::get($props, 'item.product.name') // если вдруг пришло из другой ветки
            ?? Arr::get($props, 'snapshot.product.name')
            ?? Arr::get($props, 'product_title')     // кастомный запасной ключ, если есть
            ?? null;
    }
    protected static function formatGeneric(array $p): string
    {
        $action = Arr::get($p, 'action');
        if (Arr::hasAny($p, ['old','attributes'])) {
            $diffs = [];
            foreach ((array) Arr::get($p, 'attributes', []) as $key => $new) {
                $old = Arr::get($p, "old.$key");
                if (self::shouldSkipOrderDiff((string) $key, $old, $new)) {
                    continue;
                }

                $diffs[] = self::formatOrderDiff((string) $key, $old, $new);
            }
            $diff = $diffs ? implode(' • ', $diffs) : 'Технічне оновлення';
            if ($action === 'manual_discount_changed') {
                return $diff;
            }

            return ($action ? ucfirst(str_replace('_', ' ', $action)) . ': ' : '') . $diff;
        }

        return json_encode($p, JSON_UNESCAPED_UNICODE);
    }

    private static function formatOrderDiff(string $key, mixed $old, mixed $new): string
    {
        $moneyFields = [
            'subtotal',
            'discount_total',
            'shipping_total',
            'shipping_price',
            'tax_total',
            'grand_total',
            'total_price',
        ];

        $labels = [
            'subtotal' => 'Сума товарів',
            'discount_total' => 'Знижка',
            'shipping_total' => 'Доставка',
            'shipping_price' => 'Доставка',
            'tax_total' => 'Податок',
            'grand_total' => 'Разом до сплати',
            'total_price' => 'Сума товарів',
            'payment' => 'Оплата',
            'manual_discount_percent' => 'Ручна знижка, %',
            'manual_discount_amount' => 'Ручна знижка, грн',
            'status' => __('order.journal.labels.status'),
            'notes' => __('order.journal.labels.note'),
        ];

        $label = $labels[$key] ?? str_replace('_', ' ', $key);

        if ($key === 'manual_discount_percent') {
            return $label . ': ' . self::arrowMoney($old, $new) . '%';
        }

        if ($key === 'manual_discount_amount') {
            return $label . ': ' . self::arrowMoney($old, $new) . ' грн';
        }

        $value = in_array($key, $moneyFields, true)
            ? self::arrowMoney($old, $new) . ' грн'
            : self::arrowText(self::v($old), self::v($new));

        return $label . ': ' . $value;
    }

    private static function shouldSkipOrderDiff(string $key, mixed $old, mixed $new): bool
    {
        $technicalZeroFields = [
            'sale_prc',
            'sale_sum',
            'total_price_sale',
            'tax_total',
            'has_unmatched_items',
            'kitchen_print_count',
        ];

        return in_array($key, $technicalZeroFields, true)
            && self::isEmptyTechnicalValue($old)
            && self::isEmptyTechnicalValue($new);
    }

    private static function isEmptyTechnicalValue(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return true;
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));

            return $normalized === ''
                || $normalized === '0'
                || $normalized === '0.0'
                || $normalized === '0.00'
                || strtolower($normalized) === 'false';
        }

        return is_numeric($value) && abs((float) $value) < 0.0001;
    }
}
