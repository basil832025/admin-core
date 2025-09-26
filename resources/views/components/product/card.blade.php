@props([
'title' => 'Товар',
'price' => '0.00',
'price_no_sale' => null,
'image' => '/images/no-image.svg',
])

@php
    // форматируем цену в гривнах
    $fmt = function ($val) {
        [$uah, $kop] = explode(',', number_format((float)$val, 2, ',', ' '));
        return ['uah' => $uah, 'kop' => $kop];
    };

    $p  = $fmt($price);
    $op = $price_no_sale && $price_no_sale > $price ? $fmt($price_no_sale) : null;
@endphp
<article class="desk:w-[378px] md:w-[360px] w-[355px] rounded-[12px] bg-white desk:gap-8 md:gap-4 p-3 shadow-[0_8px_20px_rgba(0,0,0,0.05)]">
    <div class="relative desk:w-[354px] h-[220px] md:w-[336px] w-[331px]  overflow-hidden rounded-[12px]">
        <img src="{{ $image }}" alt="{{ $title }}" class="h-full w-full object-cover">
        <span class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C]
             px-[10px] py-[4px] text-white font-intro font-bold
             text-[14px] leading-[16px] text-center
             shadow-[0_2px_4px_rgba(0,0,0,0.1)]">
  Знижка –22%
</span>
    </div>

    <div class="pt-4">
        <div class="flex items-start justify-between">
            <h5 class="w-[312px] font-intro font-bold text-[16px] leading-[22px] text-neutral-700">
                {{ $title }}
            </h5>
            <x-ui.favorite-button
                :product-id="1"
                :active="false ?? false"
                color="#FF7500"
                {{-- или убери, чтобы было только localStorage --}}
            />
        </div>
        <!-- Артикул -->
        <p class="w-[354px] font-intro text-[13px] leading-[16px] text-[#C04103]">
            Артикул: {{ $article ?? '123456' }}
        </p>
        <!-- Описание -->
        <p class="desk:w-[354px] md:w-[336px] w-[331px] font-intro text-[13px] leading-[16px] text-[#9CA3AF]">
            {{ $description ?? 'Дріжджове тісто (без яєць), Зелень, Телятіна, Маслини, Копчений сулугуні, Базилік, Кетчуп власного приготування, Перець чилі, Томати' }}
        </p>
        <div x-data="{ selected: '19' }" class="space-y-2 mt-3">
            <x-ui.choice-row value="19">
                <x-slot:left>
                    <x-icons.diameter/>
                    <span>19 см</span>
                    <x-icons.weight class="ml-4"/>
                    <span>350 г</span>
                </x-slot:left>

                <x-slot:right>
                    <x-icons.persons :count="1"/>
                    <span>на 1 людину</span>
                </x-slot:right>
            </x-ui.choice-row>

            <x-ui.choice-row value="23">
                <x-slot:left>
                    <x-icons.diameter/><span>23 см</span>
                    <x-icons.weight class="ml-4"/><span>600 г</span>
                </x-slot:left>
                <x-slot:right>
                    <x-icons.persons :count="2"/><span>на 2 людини</span>
                </x-slot:right>
            </x-ui.choice-row>

            <x-ui.choice-row value="29">
                <x-slot:left>
                    <x-icons.diameter/><span>29 см</span>
                    <x-icons.weight class="ml-4"/><span>900 г</span>
                </x-slot:left>
                <x-slot:right>
                    <x-icons.persons :count="3"/><span>на 3 людини</span>
                </x-slot:right>
            </x-ui.choice-row>
            <x-ui.choice-row value="33">
                <x-slot:left>
                    <x-icons.diameter/><span>33 см</span>
                    <x-icons.weight class="ml-4"/><span>1300 г</span>
                </x-slot:left>
                <x-slot:right>
                    <x-icons.persons :count="4"/><span>на 4 людини</span>
                </x-slot:right>
            </x-ui.choice-row>
            </div>

        {{-- Подвал карточки --}}
        <div class="mt-4 flex items-center justify-between">
            {{-- Левая часть: цены --}}
            <div class="flex items-baseline gap-1">
                @if($op)
                    <div class="flex items-baseline gap-1 text-neutral-400 line-through">
                        <span class="font-semibold text-[20px] leading-[20px]">{{ $op['uah'] }}</span>
                        <span class="relative -top-2 font-bold text-[12px] leading-[12px]">{{ $op['kop'] }}</span>
                        <span class="text-[14px] leading-[14px]">грн</span>
                    </div>
                @endif

                <div class="flex items-baseline gap-1 text-[#333333]">
                    <span class="font-bold text-[26px] leading-[32px]">{{ $p['uah'] }}</span>
                    <span class="relative -top-3 font-bold text-[12px] leading-[12px]">{{ $p['kop'] }}</span>
                    <span class="text-[14px] leading-[14px]">грн</span>
                </div>
            </div>

            {{-- Кнопка --}}
            <button
                type="button"
                class="inline-flex h-9 items-center text-[12px] w-[153px] h-[36px] gap-2 rounded bg-[#FF7500] px-4 font-semibold text-white shadow-[0_4px_12px_rgba(255,117,0,.35)] transition
                       hover:bg-[#ff841f] active:bg-[#e66700] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF7500]/50"
            >
                <x-icons.cart class="h-5 w-5" />
                Додати в кошик
            </button>
        </div>


    </div>

</article>
