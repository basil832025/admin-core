@php
    // Для теста без роутов
    $items = [
        'Все пироги', 'Хіти', 'Новинки', 'Сирные', 'М’ясные',
        'Постные', 'Сладкие', 'Сеты', 'Торты', 'Соусы', 'Напитки',
    ];

    // Временно считаем активным первый пункт ("Все пироги")
    $activeIndex = 0;

    // Цвет бренда из макета
    $brand = '#FF7500';
@endphp

<nav class="mt-[24px]" >
    <div class="w-[1198px] mx-auto">
    <ul class="flex items-center justify-between gap-[2px] h-6">
            @foreach ($items as $i => $label)
                @php $active = $i === $activeIndex; @endphp

                <li class="h-6">
                    <a href="#"
                       class="inline-flex items-center justify-center px-2 h-6
                              text-[14px] leading-4 font-bold transition
                              {{ $active  ? 'text-[#FF7500] border-b h-2 border-[#FF7500]' : 'text-[#C04103] border-b border-transparent hover:text-[var(--brand)] hover:border-[var(--brand)]' }}"
                       style="
                           --brand: {{ $brand }};
                           --brand-bg: {{ $brand }}1A; /* ~10% */
                           ">
                        {{ $label }}
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</nav>
