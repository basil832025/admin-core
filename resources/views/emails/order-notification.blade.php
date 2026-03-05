<x-mail::message>
@php
    $order->load([
        'items.product.parent.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristicValue',
        'adjustments',
        'clientAddress',
        'clients',
    ]);

    $orderNumber = $order->number ?? $order->id;
    $adminOrderUrl = route('filament.admin.resources.shop.orders.edit', ['record' => $order->id]);

    $deliveryAddress = '';
    if ($order->clientAddress) {
        $addrParts = [];
        if ($order->clientAddress->city) $addrParts[] = $order->clientAddress->city;
        if ($order->clientAddress->street) $addrParts[] = 'ул. ' . $order->clientAddress->street;
        if ($order->clientAddress->house) $addrParts[] = 'д. ' . $order->clientAddress->house;
        if ($order->clientAddress->apartment) $addrParts[] = 'кв. ' . $order->clientAddress->apartment;
        $deliveryAddress = implode(', ', $addrParts);
    }

    if ($order->payment) {
        if ($order->payment === \App\Enums\PaymentMethodEnum::LIQPAY || $order->payment->value === 11) {
            $paymentLabel = 'LiqPay';
        } elseif ($order->payment === \App\Enums\PaymentMethodEnum::CARD || $order->payment->value === 1) {
            $paymentLabel = 'Картой при получении';
        } elseif ($order->payment === \App\Enums\PaymentMethodEnum::CASH || $order->payment->value === 2) {
            $paymentLabel = 'Наличными';
        } else {
            $paymentLabel = (string) $order->payment->label('ru');
        }
    } else {
        $paymentLabel = 'Картой при получении';
    }

    $items = $order->items;
    $itemsTotal = (float) ($order->total_price ?? 0);
    $shipping = (float) ($order->shipping_price ?? 0);

    $adjustments = $order->adjustments()->whereNull('shop_order_item_id')->get();
    $discountsList = [];
    $discountTotal = 0.0;
    foreach ($adjustments as $adj) {
        $amount = (float) ($adj->amount ?? 0);
        if ($amount < 0) {
            $discountTotal += abs($amount);
            $discountsList[] = [
                'label' => $adj->label ?? 'Скидка',
                'amount' => abs($amount),
            ];
        }
    }

    $bonusesSpent = max(0, (float) ($order->sale_sum ?? 0));
    $bonusesEarned = 0;
    if (method_exists($order, 'loyaltyTransactions')) {
        $bonusesEarned = (float) $order->loyaltyTransactions()
            ->where('type', \App\Models\Shop\LoyaltyTransaction::TYPE_ACCRUAL)
            ->where('source', 'order')
            ->sum('amount');
    }

    $total = (float) ($order->grand_total ?? ($itemsTotal - $bonusesSpent + $shipping));
@endphp

# Новый заказ №{{ $orderNumber }}

Поступил новый заказ с сайта.

## Информация о заказе

**Номер заказа:** №{{ $orderNumber }}  
**Дата создания:** {{ $order->created_at->format('d.m.Y H:i') }}  
**Статус:** {{ $order->status->getLabel() }}

## Информация о клиенте

@if($order->clients)
**Имя:** {{ $order->clients->name ?? '—' }}  
**Телефон:** {{ $order->clients->phone ?? '—' }}  
**Email:** {{ $order->clients->email ?? '—' }}
@else
**Имя:** {{ $order->short_name ?? '—' }}
@endif

## Адрес доставки

{{ $deliveryAddress ?: '—' }}

## Способ доставки

{{ $order->self_pickup ? 'Самовывоз' : 'Доставка курьером' }}

@if($order->date_order)
**Дата доставки:** {{ \Carbon\Carbon::parse($order->date_order)->format('d.m.Y') }}
@endif

@if($order->time_order)
**Время доставки:** {{ $order->time_order }}
@endif

## Способ оплаты

{{ $paymentLabel }}

## Товары

<x-mail::table>
| Название | Количество | Цена за единицу | Сумма |
|:---------|:----------:|:---------------:|:-----:|
@foreach($items as $item)
@php
    $product = $item->product;
    $snapshot = $item->product_snapshot ?? [];
    $name = $snapshot['name'] ?? $snapshot['title'] ?? null;

    if (!$name && $product) {
        $parent = $product->parent ?? $product;
        $name = $parent->display_name ?? $parent->displayName ?? $parent->title ?? 'Товар';
    }
    $name = $name ?? 'Товар';

    $productChars = [];
    $personSlug = 'persons';

    if ($product && $product->relationLoaded('productCharacteristicValues')) {
        $charValues = $product->productCharacteristicValues
            ->filter(function($cv) use ($personSlug) {
                $char = $cv->characteristic;
                if (!$char) return false;
                return $char->is_main_tab && $char->is_active && ($char->slug ?? null) !== $personSlug;
            });

        foreach ($charValues as $cv) {
            $char = $cv->characteristic;
            if (!$char) continue;
            $value = $cv->value_text ?? ($cv->characteristicValue->value ?? null);
            if ($value) {
                $productChars[] = $value;
            }
        }
    } elseif ($product) {
        $charValues = $product->productCharacteristicValues()
            ->whereHas('characteristic', function($q) use ($personSlug) {
                $q->where('is_main_tab', 1)
                    ->where('is_active', 1)
                    ->where('slug', '!=', $personSlug);
            })
            ->with(['characteristic.svgImage', 'characteristicValue'])
            ->get();

        foreach ($charValues as $cv) {
            $char = $cv->characteristic;
            if (!$char) continue;
            $value = $cv->value_text ?? ($cv->characteristicValue->value ?? null);
            if ($value) {
                $productChars[] = $value;
            }
        }
    }

    if (empty($productChars)) {
        $characteristics = $snapshot['characteristics'] ?? [];
        if (!empty($characteristics) && is_array($characteristics)) {
            foreach ($characteristics as $char) {
                if (is_array($char)) {
                    $charSlug = $char['slug'] ?? null;
                    if ($charSlug === 'persons' || $charSlug === 'osoby') {
                        continue;
                    }
                    $charValue = $char['value'] ?? $char['text'] ?? null;
                    if ($charValue) {
                        $productChars[] = $charValue;
                    }
                } elseif (is_string($char)) {
                    $productChars[] = $char;
                }
            }
        }
    }

    if (!empty($productChars)) {
        $name .= ' (' . implode(', ', $productChars) . ')';
    }

    $qty = (int)($item->qty ?? 1);
    $price = (float)($item->unit_price ?? 0);
    if (!empty($item->subtotal) && (float)$item->subtotal > 0) {
        $subtotal = (float)$item->subtotal;
    } elseif (!empty($item->total) && (float)$item->total > 0) {
        $subtotal = (float)$item->total;
    } else {
        $subtotal = $qty * $price;
    }
@endphp
| {{ $name }} | {{ $qty }} шт. | {{ number_format($price, 2, '.', ' ') }} грн | {{ number_format($subtotal, 2, '.', ' ') }} грн |
@endforeach
</x-mail::table>

## Итого

**Товары:** {{ number_format($itemsTotal, 2, '.', ' ') }} грн

**Скидка:** -{{ number_format($discountTotal, 2, '.', ' ') }} грн

**Доставка:** {{ number_format($shipping, 2, '.', ' ') }} грн

@if(!empty($discountsList))
@foreach($discountsList as $discount)
**{{ $discount['label'] }}:** -{{ number_format($discount['amount'], 2, '.', ' ') }} грн
@endforeach
@endif

**Списано бонусов:** {{ number_format($bonusesSpent, 2, '.', ' ') }} грн

@if($bonusesEarned > 0)
**Начислено бонусов:** {{ number_format($bonusesEarned, 2, '.', ' ') }} бонусов
@endif

**Итого к оплате:** {{ number_format($total, 2, '.', ' ') }} грн

@if($order->notes)
## Комментарий клиента

{{ $order->notes }}
@endif

<x-mail::button :url="$adminOrderUrl">
Открыть заказ в админке
</x-mail::button>

С уважением, команда «Три Пироги».
</x-mail::message>
