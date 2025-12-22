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
                    Спасибо! 🎉
                </div>
                <div class="text-[28px] leading-[32px] font-semibold text-[#111827]">
                    Ваш заказ отправлен
                </div>
            </div>

            {{-- Сетка картинок 3×3 --}}
            {{-- Сетка товаров --}}
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 gap-x-10 gap-y-8 justify-items-center  mb-10">
                @foreach($items as $it)
                    @php
                        $p      = $it->product;
                        $parent = $p?->parent ?: $p;

                        $name  = $parent?->display_name ?? $parent?->displayName ?? $parent?->title ?? 'Товар';
                        $image = $parent?->main_image_url
                              ?? $parent?->image_url
                              ?? $p?->main_image_url
                              ?? $p?->image_url
                              ?? asset('images/placeholder-4x3.jpg');
                    @endphp

                    <div class="relative flex items-center">
                        {{-- Номер позиции --}}
                        <div
                            class="inline-flex items-center justify-center
               w-8 h-8 min-w-[32px] min-h-[32px] flex-shrink-0
               rounded-full bg-[#FF7500] text-white
               text-[14px] font-semibold mr-3"
                        >
                            {{ $loop->iteration }}
                        </div>

                        {{-- картинка товара --}}
                        <img
                            src="{{ $image }}"
                            alt="{{ $name }}"
                            class="w-[120px] h-[96px] rounded-[8px] object-cover"
                        >
                    </div>
                @endforeach
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

                <div class="text-right font-medium text-[#929292]">Код заказа:</div>
                <div class="text-[#111827]">#{{ $order->number }}</div>

                <div class="text-right font-medium text-[#929292]">Дата:</div>
                <div class="text-[#111827]">
                    {{ \Carbon\Carbon::parse($order->created_at)->format('d.m.Y') }}
                </div>

                <div class="text-right font-medium text-[#929292]">Сумма к оплате:</div>
                <div class="text-[#111827]">{{ $total }} грн</div>

                <div class="text-right font-medium text-[#929292]">Способ оплаты:</div>
                <div class="text-[#111827]">{{ $payLabel }}</div>

            </div>



            {{-- Кнопка "Вернуться на Главную" — как в Figma --}}
            <a href="{{ url('/') }}"
               class="block mx-auto text-center
          w-full max-w-[560px] h-[46px]
          bg-[#FF7500] text-white font-semibold text-[16px]
          rounded-[6px] leading-[46px] shadow
          hover:bg-[#e86a00] transition">
                Вернуться на Главную
            </a>


            {{-- Кнопка "Продублировать заказ на Email" --}}
            <a href="#"
                        class="block mx-auto text-center w-full max-w-[560px] h-[40px] mt-2
                   border border-[#E5E7EB] bg-white
                   rounded-[6px]
                   text-[#FF7500] text-[14px] font-semibold
                   leading-[40px] hover:bg-gray-50 transition">
                    Продублировать заказ на Email
                </a>


        </div>
    </div>
@endsection
