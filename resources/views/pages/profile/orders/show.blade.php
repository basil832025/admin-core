@php
    $order->load([
        'items.product.parent.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristic.svgImage',
        'items.product.productCharacteristicValues.characteristicValue',
        'clientAddress', 
        'clients'
    ]);
    
    $orderDate = $order->created_at;
    $day = $orderDate->format('d');
    $monthNames = [
        '01' => st('profile.bonuses.jan', 'Янв'),
        '02' => st('profile.bonuses.feb', 'Фев'),
        '03' => st('profile.bonuses.mar', 'Мар'),
        '04' => st('profile.bonuses.apr', 'Апр'),
        '05' => st('profile.bonuses.may', 'Май'),
        '06' => st('profile.bonuses.jun', 'Июн'),
        '07' => st('profile.bonuses.jul', 'Июл'),
        '08' => st('profile.bonuses.aug', 'Авг'),
        '09' => st('profile.bonuses.sep', 'Сен'),
        '10' => st('profile.bonuses.oct', 'Окт'),
        '11' => st('profile.bonuses.nov', 'Ноя'),
        '12' => st('profile.bonuses.dec', 'Дек'),
    ];
    $month = $monthNames[$orderDate->format('m')] ?? $orderDate->format('M');
    
    $statusColors = $order->status->getFrontendColors();
    $statusLabel = $order->status->getLabel();
    
    $items = $order->items;
    $total = $order->grand_total ?? $order->total_price ?? 0;
    
    // Бонусы из транзакций
    $bonusAmount = 0;
    if ($order->loyaltyTransactions) {
        $accrualTransactions = $order->loyaltyTransactions()->where('type', 'accrual')->get();
        $bonusAmount = $accrualTransactions->sum('amount');
    }
    
    // Адрес доставки
    $deliveryAddress = '';
    if ($order->clientAddress) {
        $addrParts = [];
        if ($order->clientAddress->city) $addrParts[] = $order->clientAddress->city;
        if ($order->clientAddress->street) $addrParts[] = $order->clientAddress->street;
        if ($order->clientAddress->house) $addrParts[] = st('address.parts.house_short', 'д.') . $order->clientAddress->house;
        if ($order->clientAddress->apartment) $addrParts[] = st('address.parts.apartment_short', 'кв.') . $order->clientAddress->apartment;
        $deliveryAddress = implode(', ', $addrParts);
    }
@endphp

@extends('layouts.app')

@section('title', st('profile.orders.details_title', 'Детали заказа'))

@section('content')
    <div class="mx-auto desk:w-[1200px] px-4 md:px-6 desk:px-0">
        <div class="xl:grid xl:grid-cols-[240px,1fr] md:gap-6">
            {{-- Левое меню (desktop) --}}
            <aside class="hidden xl:block">
                @include('pages.menu.profile-menu')
            </aside>

            {{-- Контент --}}
            <main>
                {{-- Кнопка "Назад" --}}
                <a href="{{ route('profile.orders.index') }}" 
                   class="inline-flex items-center gap-2 text-[#FF7500] hover:text-orange-600 transition mb-4">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    {{ st('profile.orders.back_to_history', 'Назад к истории заказов') }}
                </a>

                {{-- Заголовок: Номер и дата --}}
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h1 class="text-[28px] font-bold text-[#19191A] mb-1">
                            No {{ $order->id }} от {{ $day }} {{ $month }}.
                        </h1>
                    </div>
                    {{-- Статус --}}
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[3px] text-[14px] font-medium shadow-[0_2px_4px_rgba(0,0,0,0.1)]" 
                          style="background-color: {{ $statusColors['bg'] }}; color: {{ $statusColors['text'] }};">
                        <x-order-status-icon :status="$order->status" />
                        {{ $statusLabel }}
                    </span>
                </div>

                {{-- Основной контент: две колонки на больших экранах, одна на мобильных --}}
                <div class="grid grid-cols-1 xl:grid-cols-[477px,397px] gap-6">
                    {{-- Левая колонка: Товары (шире) --}}
                    <div class="space-y-4">
                        @foreach($items as $item)
                            @php
                                $product = $item->product;
                                
                                // Если товар удален, используем snapshot
                                $snapshot = $item->product_snapshot ?? [];
                                $name = $snapshot['name'] ?? $snapshot['title'] ?? null;
                                
                                if (!$name && $product) {
                                    $parent = $product->parent ?? $product;
                                    $name = $parent->display_name ?? $parent->displayName ?? $parent->title ?? 'Товар';
                                }
                                $name = $name ?? 'Товар';
                                
                                $image = $snapshot['image'] ?? $snapshot['main_image_url'] ?? null;
                                if (!$image && $product) {
                                    $parent = $product->parent ?? $product;
                                    $image = $parent->main_image_url
                                          ?? $parent->image_url
                                          ?? $product->main_image_url
                                          ?? $product->image_url;
                                }
                                $image = $image ?? asset('images/placeholder-4x3.jpg');
                                
                                $qty = $item->qty ?? 1;
                                $price = $item->unit_price ?? $item->price ?? 0;
                                $subtotal = $item->subtotal ?? $item->total ?? ($qty * $price);
                                
                                // Получаем старую цену
                                $oldPrice = null;
                                if ($product) {
                                    $parent = $product->parent ?? $product;
                                    $oldPrice = $parent->old_price ?? $product->old_price ?? null;
                                }
                                if (!$oldPrice && !empty($snapshot)) {
                                    $oldPrice = $snapshot['old_price'] ?? null;
                                }
                                
                                // Получаем характеристики с иконками
                                $productChars = [];
                                
                                // Пытаемся получить из модели продукта (используем уже загруженные связи)
                                // Исключаем характеристику "персоны" (slug = 'persons')
                                $personSlug = 'persons';
                                if ($product && $product->relationLoaded('productCharacteristicValues')) {
                                    $charValues = $product->productCharacteristicValues
                                        ->filter(function($cv) use ($personSlug) {
                                            $char = $cv->characteristic;
                                            if (!$char) return false;
                                            // Исключаем персоны, оставляем только размер и вес
                                            return $char->is_main_tab && $char->is_active && ($char->slug ?? null) !== $personSlug;
                                        });
                                    
                                    foreach ($charValues as $cv) {
                                        $char = $cv->characteristic;
                                        if (!$char) continue;
                                        
                                        $value = $cv->value_text ?? ($cv->characteristicValue->value ?? null);
                                        if ($value) {
                                            $productChars[] = [
                                                'value' => $value,
                                                'svg' => $char->svgImage?->url ?? null,
                                            ];
                                        }
                                    }
                                } elseif ($product) {
                                    // Если связи не загружены, пытаемся загрузить (fallback)
                                    $charValues = $product->productCharacteristicValues()
                                        ->whereHas('characteristic', function($q) use ($personSlug) {
                                            $q->where('is_main_tab', 1)
                                              ->where('is_active', 1)
                                              ->where('slug', '!=', $personSlug); // Исключаем персоны
                                        })
                                        ->with(['characteristic.svgImage', 'characteristicValue'])
                                        ->get();
                                    
                                    foreach ($charValues as $cv) {
                                        $char = $cv->characteristic;
                                        if (!$char) continue;
                                        
                                        $value = $cv->value_text ?? ($cv->characteristicValue->value ?? null);
                                        if ($value) {
                                            $productChars[] = [
                                                'value' => $value,
                                                'svg' => $char->svgImage?->url ?? null,
                                            ];
                                        }
                                    }
                                }
                                
                                // Если не нашли в модели, пробуем из snapshot (исключаем персоны)
                                if (empty($productChars)) {
                                    $characteristics = $snapshot['characteristics'] ?? [];
                                    if (!empty($characteristics) && is_array($characteristics)) {
                                        foreach ($characteristics as $char) {
                                            // Пропускаем характеристику "персоны" (slug = 'persons')
                                            if (is_array($char)) {
                                                $charSlug = $char['slug'] ?? null;
                                                if ($charSlug === 'persons' || $charSlug === 'osoby') {
                                                    continue; // Пропускаем персоны
                                                }
                                                $productChars[] = [
                                                    'value' => $char['value'] ?? $char['text'] ?? null,
                                                    'svg' => $char['svg'] ?? $char['svg_url'] ?? null,
                                                ];
                                            } elseif (is_string($char)) {
                                                $productChars[] = [
                                                    'value' => $char,
                                                    'svg' => null,
                                                ];
                                            }
                                        }
                                    }
                                }
                                
                                // Фильтруем пустые значения
                                $productChars = array_filter($productChars, fn($c) => !empty($c['value']));
                            @endphp
                            <div class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.08)] p-4">
                                <div class="flex gap-4">
                                    {{-- Изображение --}}
                                    <img src="{{ $image }}" alt="{{ $name }}" 
                                         class="w-[120px] h-[96px] rounded-[8px] object-cover flex-shrink-0">
                                    
                                    {{-- Информация о товаре --}}
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-[14px] font-semibold text-[#19191A] mb-2 line-clamp-2">
                                            {{ $name }}
                                        </h3>
                                        
                                        {{-- Характеристики товара с иконками (19 см, 350 г) --}}
                                        @if(!empty($productChars))
                                            <div class="flex items-center gap-3 text-[12px] text-gray-500 mb-2">
                                                @foreach($productChars as $charData)
                                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                                                        @if(!empty($charData['svg']))
                                                            <span aria-hidden="true" class="inline-block h-4 w-4"
                                                                  style="background-color: currentColor;
                                                                         mask-image:url('{{ $charData['svg'] }}');-webkit-mask-image:url('{{ $charData['svg'] }}');
                                                                         mask-repeat:no-repeat;-webkit-mask-repeat:no-repeat;
                                                                         mask-position:center;-webkit-mask-position:center;
                                                                         mask-size:contain;-webkit-mask-size:contain;"></span>
                                                        @endif
                                                        <span>{{ $charData['value'] }}</span>
                                                    </span>
                                                    @if(!$loop->last)
                                                        <span>•</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    
                                    {{-- Правая часть: Количество и цена --}}
                                    <div class="flex flex-col items-end justify-between flex-shrink-0">
                                        {{-- Количество --}}
                                        <div class="text-[14px] font-semibold text-[#19191A] mb-2">
                                            {{ $qty }} {{ st('profile.orders.items_pcs', 'шт') }}
                                        </div>
                                        
                                        {{-- Цена --}}
                                        @php
                                            $priceFormatted = number_format($subtotal, 1, '.', ' ');
                                            $parts = explode('.', $priceFormatted);
                                            $uah = $parts[0];
                                            $kop = isset($parts[1]) ? substr($parts[1], 0, 1) : '0';
                                            
                                            // Форматируем старую цену
                                            $oldPriceFormatted = null;
                                            $oldUah = null;
                                            $oldKop = null;
                                            if ($oldPrice && $oldPrice > $subtotal) {
                                                $oldPriceTotal = $oldPrice * $qty;
                                                $oldPriceFormatted = number_format($oldPriceTotal, 1, '.', ' ');
                                                $oldParts = explode('.', $oldPriceFormatted);
                                                $oldUah = $oldParts[0];
                                                $oldKop = isset($oldParts[1]) ? substr($oldParts[1], 0, 1) : '0';
                                            }
                                        @endphp
                                        <div class="text-right">
                                            @if($oldPriceFormatted)
                                                <div class="text-[14px] text-gray-400 line-through mb-1">
                                                    {{ $oldUah }}<sup class="text-[12px]">{{ $oldKop }}</sup> {{ st('profile.orders.uah', 'грн') }}
                                                </div>
                                            @endif
                                            <div class="text-[16px] font-semibold text-[#DC2626]">
                                                {{ $uah }}<sup class="text-[14px]">{{ $kop }}</sup> {{ st('profile.orders.uah', 'грн') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Правая колонка: Информация о заказе (уже) --}}
                    <div class="space-y-4">
                        {{-- Статус заказа --}}
                        <div class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.08)] p-4 md:p-6">
                            <h2 class="text-[16px] font-semibold text-[#19191A] mb-3">
                                {{ $statusLabel }}
                            </h2>
                            <p class="text-[14px] text-gray-600 mb-3">
                                {{ st('profile.orders.thank_you', 'Спасибо за заказ!') }}
                            </p>
                            
                            {{-- Доставлен с датой и временем --}}
                            @php
                                $deliveryDate = $order->date_order ?? null;
                                $deliveryTime = $order->time_order ?? null;
                                $deliveryDateTime = null;
                                if ($deliveryDate) {
                                    $date = \Carbon\Carbon::parse($deliveryDate);
                                    $monthNames = [
                                        '01' => st('profile.bonuses.jan', 'Янв'),
                                        '02' => st('profile.bonuses.feb', 'Фев'),
                                        '03' => st('profile.bonuses.mar', 'Мар'),
                                        '04' => st('profile.bonuses.apr', 'Апр'),
                                        '05' => st('profile.bonuses.may', 'Май'),
                                        '06' => st('profile.bonuses.jun', 'Июн'),
                                        '07' => st('profile.bonuses.jul', 'Июл'),
                                        '08' => st('profile.bonuses.aug', 'Авг'),
                                        '09' => st('profile.bonuses.sep', 'Сен'),
                                        '10' => st('profile.bonuses.oct', 'Окт'),
                                        '11' => st('profile.bonuses.nov', 'Ноя'),
                                        '12' => st('profile.bonuses.dec', 'Дек'),
                                    ];
                                    $day = $date->format('d');
                                    $month = $monthNames[$date->format('m')] ?? $date->format('M');
                                    $timeStr = '';
                                    if ($deliveryTime) {
                                        $time = is_string($deliveryTime) ? $deliveryTime : \Carbon\Carbon::parse($deliveryTime)->format('H:i');
                                        // Форматируем время как диапазон (например, 11:55-12:00)
                                        $timeParts = explode('-', $time);
                                        if (count($timeParts) === 2) {
                                            $timeStr = $timeParts[0] . '-' . $timeParts[1];
                                        } else {
                                            // Если нет диапазона, добавляем +5 минут
                                            $startTime = \Carbon\Carbon::createFromFormat('H:i', $time);
                                            $endTime = $startTime->copy()->addMinutes(5);
                                            $timeStr = $startTime->format('H:i') . '-' . $endTime->format('H:i');
                                        }
                                    }
                                    $deliveryDateTime = $timeStr ? $timeStr . ', ' . $day . ' ' . strtolower($month) . '.' : $day . ' ' . strtolower($month) . '.';
                                }
                            @endphp
                            @if($deliveryDateTime)
                                <div class="flex items-center justify-between">
                                    <span class="text-[14px] text-gray-500">
                                        {{ st('profile.orders.delivered', 'Доставлен') }}
                                    </span>
                                    <span class="text-[14px] font-semibold text-[#19191A]">
                                        {{ $deliveryDateTime }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Информация о заказе в 2 колонки (единый блок с разделительными линиями) --}}
                        <div class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.08)] p-4 md:p-6">
                            <div class="space-y-3">
                                @if($order->clients)
                                    <div class="flex justify-between">
                                        <span class="text-[14px] text-[#19191A]">
                                            {{ st('profile.orders.recipient', 'Получатель') }}
                                        </span>
                                        <span class="text-[14px] text-gray-500">
                                            {{ $order->clients->name ?? '—' }}
                                        </span>
                                    </div>
                                @endif

                                @if($deliveryAddress)
                                    <div class="flex justify-between">
                                        <span class="text-[14px] text-[#19191A]">
                                            {{ st('profile.orders.address', 'Адрес') }}
                                        </span>
                                        <span class="text-[14px] text-gray-500 text-right">
                                            {{ $deliveryAddress }}
                                        </span>
                                    </div>
                                @endif

                                <div class="flex justify-between">
                                    <span class="text-[14px] text-[#19191A]">
                                        {{ st('profile.orders.delivery_method', 'Способ получения') }}
                                    </span>
                                    <span class="text-[14px] text-gray-500">
                                        {{ $order->self_pickup ? st('profile.orders.delivery.pickup', 'Самовывоз') : st('profile.orders.delivery.courier', 'Доставка курьером') }}
                                    </span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-[14px] text-[#19191A]">
                                        {{ st('profile.orders.payment_method', 'Способ оплаты') }}
                                    </span>
                                    <span class="text-[14px] text-gray-500">
                                        @if($order->payment)
                                            @if($order->payment === \App\Enums\PaymentMethodEnum::CARD || $order->payment->value === 1)
                                                {{ st('profile.orders.payment.card', 'Оплата картой') }}
                                            @elseif($order->payment === \App\Enums\PaymentMethodEnum::CASH || $order->payment->value === 2)
                                                {{ st('profile.orders.payment.cash', 'Оплата наличными') }}
                                            @elseif($order->payment === \App\Enums\PaymentMethodEnum::LIQPAY || $order->payment->value === 11)
                                                {{ st('profile.orders.payment.online', 'Онлайн оплата') }}
                                            @else
                                                {{ $order->payment->label() }}
                                            @endif
                                        @else
                                            {{ st('profile.orders.payment.card', 'Оплата картой') }}
                                        @endif
                                    </span>
                                </div>

                                {{-- Разделительная линия --}}
                                <div class="border-t border-gray-200 my-3"></div>

                                <div class="flex justify-between">
                                    <span class="text-[14px] text-gray-600">
                                        {{ st('profile.orders.goods', 'Товары') }}
                                    </span>
                                    <span class="text-[14px] font-semibold text-[#19191A]">
                                        {{ number_format($total, 0, '.', ' ') }} {{ st('profile.orders.uah', 'грн') }}
                                    </span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-[16px] font-semibold text-[#19191A]">
                                        {{ st('profile.orders.total_to_pay', 'Итого к оплате') }}
                                    </span>
                                    <span class="text-[16px] font-semibold text-[#19191A]">
                                        {{ number_format($total, 0, '.', ' ') }} {{ st('profile.orders.uah', 'грн') }}
                                    </span>
                                </div>

                                @if($bonusAmount > 0)
                                    <div class="flex justify-between">
                                        <span class="text-[14px] text-gray-600">
                                            {{ st('profile.orders.bonuses_title', 'Бонусы') }}
                                        </span>
                                        <span class="text-[14px] font-semibold text-[#16A34A]">
                                            +{{ number_format($bonusAmount, 0, '.', ' ') }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Кнопка "Повторить заказ" --}}
                        <button type="button" 
                                class="w-full h-[46px] rounded-[6px] text-[14px] font-semibold text-white bg-[#FF7500] hover:bg-orange-600 transition shadow-[0_2px_10px_rgba(0,0,0,0.08)]">
                            {{ st('profile.orders.repeat', 'Повторить заказ') }}
                        </button>
                    </div>
                </div>
            </main>
        </div>
    </div>
@endsection
