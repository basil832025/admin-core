@props([
// подписи
'labels' => [
'popular'       => 'Популярні',
'new'           => 'Новинки',
'price_asc'     => 'Ціна: зростання',
'price_desc'    => 'Ціна: спадання',
'discount_asc'  => 'Знижка: зростання',
'discount_desc' => 'Знижка: спадання',
],
// имя параметра в query string
'param' => 'sort',
// текущее значение (если не передали — возьмём из query)
'value' => null,
// ширина по макету
'width' => 'w-[267px]',
])

@php
    $current = $value ?? request($param, array_key_first($labels));
@endphp

<div {{ $attributes->merge(['class' => "relative $width"]) }} x-data="{ open:false }">
    <!-- Кнопка -->
    <button type="button" @click="open = !open"
            class="w-full h-10 rounded-[12px] border border-[#E5E7EB] bg-white
                 px-3 flex items-center justify-between">
    <span class="font-bold text-[16px] leading-none text-[#19191A]">
      {{ $labels[$current] ?? 'Сортувати' }}
    </span>
        <svg class="w-5 h-5 text-[#19191A] transition-transform"
             :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <!-- Меню -->
    <div x-show="open" x-transition.origin.top.left @click.outside="open=false"
         class="absolute z-20 mt-2 w-full bg-white rounded-2xl border border-[#E5E7EB] shadow-lg p-2">
        @foreach($labels as $key => $label)
            <a href="{{ request()->fullUrlWithQuery([$param => $key]) }}"
               @click="open=false"
               class="block px-3 py-2 rounded-[10px] text-[15px] text-[#19191A]
                hover:bg-neutral-100 {{ $current === $key ? 'bg-[#FFE6B8] font-semibold' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>
