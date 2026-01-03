<x-mail::message>
# Новый заказ №{{ $order->id }}

Поступил новый заказ с сайта.

## Информация о заказе

**Номер заказа:** №{{ $order->id }}  
**Дата создания:** {{ $order->created_at->format('d.m.Y H:i') }}  
**Статус:** {{ $order->status->getLabel() }}

## Информация о клиенте

@if($order->clients)
**Имя:** {{ $order->clients->name ?? '—' }}  
**Телефон:** {{ $order->clients->phone ?? '—' }}  
@if($order->clients->email)
**Email:** {{ $order->clients->email }}
@endif
@else
**Имя:** {{ $order->short_name ?? '—' }}
@endif

## Адрес доставки

@php
    $deliveryAddress = '';
    if ($order->clientAddress) {
        $addrParts = [];
        if ($order->clientAddress->city) $addrParts[] = $order->clientAddress->city;
        if ($order->clientAddress->street) $addrParts[] = $order->clientAddress->street;
        if ($order->clientAddress->house) $addrParts[] = 'д.' . $order->clientAddress->house;
        if ($order->clientAddress->apartment) $addrParts[] = 'кв.' . $order->clientAddress->apartment;
        $deliveryAddress = implode(', ', $addrParts);
    }
@endphp

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

@if($order->payment)
    @if($order->payment === \App\Enums\PaymentMethodEnum::CARD || $order->payment->value === 1)
        Оплата картой
    @elseif($order->payment === \App\Enums\PaymentMethodEnum::CASH || $order->payment->value === 2)
        Оплата наличными
    @elseif($order->payment === \App\Enums\PaymentMethodEnum::LIQPAY || $order->payment->value === 11)
        Онлайн оплата
    @else
        {{ $order->payment->label() }}
    @endif
@else
    Оплата картой
@endif

## Товары

@php
    $order->load([
        'items.product.parent.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristicValue',
        'adjustments'
    ]);
    $items = $order->items;
    
    // Сумма товаров без скидок
    $itemsTotal = (float)($order->total_price ?? 0);
    
    // Все скидки (adjustments с отрицательными amount)
    // Берем только adjustments на уровне заказа (не на уровне товара)
    $adjustments = $order->adjustments()->whereNull('shop_order_item_id')->get();
    $discountTotal = 0;
    $discountsList = [];
    
    foreach ($adjustments as $adj) {
        $amount = (float)($adj->amount ?? 0);
        if ($amount < 0) {
            $discountAmount = abs($amount);
            $discountTotal += $discountAmount;
            $discountsList[] = [
                'label' => $adj->label ?? 'Скидка',
                'amount' => $discountAmount,
            ];
        }
    }
    
    // Итоговая сумма с учетом скидок
    $total = $order->grand_total ?? ($itemsTotal - $discountTotal);
@endphp

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
    
    // Получаем характеристики (размер, вес, но не персоны)
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
    
    // Если не нашли в модели, пробуем из snapshot (исключаем персоны)
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
    
    // Добавляем характеристики к названию
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

@if(!empty($discountsList))
@foreach($discountsList as $discount)
**{{ $discount['label'] }}:** -{{ number_format($discount['amount'], 2, '.', ' ') }} грн
@endforeach
@endif

**Итого к оплате:** {{ number_format($total, 2, '.', ' ') }} грн

@if($order->notes)
## Комментарии

{{ $order->notes }}
@endif

Спасибо за использование нашего сервиса!
</x-mail::message>
