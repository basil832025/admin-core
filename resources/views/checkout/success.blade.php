@extends('layouts.app')

@section('title','Ваш заказ отправлен')

@section('content')
    {{-- Оверлей с затемнением, перекрывает всю страницу --}}
    <div class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm flex items-start justify-center overflow-y-auto">
        <div
            class="w-full max-w-[750px] bg-white rounded-[8px]
               shadow-[0_32px_48px_rgba(0,0,0,0.10)]
               mt-20 mb-10 px-6 sm:px-10 pt-[80px] pb-[80px]"
        >

            {{-- Заголовок --}}
            <div class="text-center mb-10">
                <div class="text-[24px] leading-[28px] font-semibold text-[#6B7280] mb-2">
                    {{ st('order.success.thank_you', 'Спасибо!') }} 🎉
                </div>
                <div class="text-[28px] leading-[32px] font-semibold text-[#111827]">
                    {{ st('order.success.order_sent', 'Ваш заказ отправлен') }}
                </div>
            </div>

            {{-- Текст благодарности вместо картинок --}}
            <div class="text-center mb-10 space-y-4">
                @if($isWorkingHours)
                    {{-- В рабочее время --}}
                    <p class="text-[16px] leading-[24px] text-[#4B5563]">
                        {{ st('order.success.working_hours.thank_you', 'Благодарим Вас за заказ.') }}<br>
                        {{ st('order.success.working_hours.order_number', 'Номер заказа') }} {{ $orderNumber }}<br>
                        {{ st('order.success.working_hours.call_center', 'В течении 15 минут с Вами свяжется оператор колл центра для подтверждения заказа.') }}
                    </p>
                @else
                    {{-- В нерабочее время --}}
                    <p class="text-[16px] leading-[24px] text-[#4B5563]">
                        {{ st('order.success.non_working_hours.thank_you', 'Благодарим Вас за заказ.') }}<br>
                        {{ st('order.success.non_working_hours.order_number', 'Номер заказа') }} {{ $orderNumber }}<br>
                        {{ st('order.success.non_working_hours.call_center', 'С Вами завтра с 08:30 свяжется оператор колл центра для подтверждения заказа.') }}
                    </p>
                @endif
                <p class="text-[16px] leading-[24px] text-[#4B5563] mt-6">
                    {{ st('order.success.signature', 'С уважением, команда «Три Пироги»') }}
                </p>
            </div>

            {{-- Информация о заказе --}}
            @php
                $total   = (float)($order->total_price_sale ?? $order->total_price ?? 0);
                $date    = $order->date_order ?? $order->dat ?? $order->created_at;
                $dateStr = $date ? \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') : '';
                $number  = $order->number ?? ('#'.str_pad($order->id, 5, '0', STR_PAD_LEFT));

                $payLabel = $order->payment?->label(app()->getLocale()) ?? '—';
            @endphp


            {{-- Информация о заказе — по центру, как в Figma --}}
            <div class="grid grid-cols-[auto_auto] gap-x-10 gap-y-3 justify-center text-base leading-[20px] text-[#4B5563] mb-10">

                <div class="text-right font-medium text-[#929292]">{{ st('order.success.order_code', 'Код заказа') }}:</div>
                <div class="text-[#111827]">#{{ $order->number }}</div>

                <div class="text-right font-medium text-[#929292]">{{ st('order.success.date', 'Дата') }}:</div>
                <div class="text-[#111827]">
                    {{ \Carbon\Carbon::parse($order->created_at)->format('d.m.Y') }}
                </div>

                <div class="text-right font-medium text-[#929292]">{{ st('order.success.amount_to_pay', 'Сумма к оплате') }}:</div>
                <div class="text-[#111827]">{{ $total }} грн</div>

                <div class="text-right font-medium text-[#929292]">{{ st('order.success.payment_method', 'Способ оплаты') }}:</div>
                <div class="text-[#111827]">{{ $payLabel }}</div>

            </div>



            {{-- Кнопка "Вернуться на Главную" — как в Figma --}}
            <a href="{{ url('/') }}"
               class="block mx-auto text-center
          w-full max-w-[560px] h-[46px]
          bg-[#FF7500] text-white font-semibold text-[16px]
          rounded-[6px] leading-[46px] shadow
          hover:bg-[#e86a00] transition">
                {{ st('order.success.back_to_home', 'Вернуться на Главную') }}
            </a>


        </div>
    </div>
@endsection
