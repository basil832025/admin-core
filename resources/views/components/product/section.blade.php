@props([
'title' => 'Хіти',
// сейчас можно передавать простой массив; позже будет коллекция моделей
'items' => [],
// опционально «Показати все»
'moreUrl' => null,
])

<section class="max-w-screen-xl mx-auto">
    <div class="flex items-end justify-between ">
        <x-product.section-title>{{ $title }}</x-product.section-title>

        @if ($moreUrl)
            <a href="{{ $moreUrl }}" class="text-[#FF7500] hover:underline">Показати все</a>
        @endif
    </div>

    <!-- отступ 32px до сетки -->
    <div class="md:mt-8 mt-6 grid grid-cols-1 desk:gap-12 md:gap-8 sm:grid-cols-2 lg:grid-cols-3 ">
        @forelse ($items as $p)
            <x-product.card
                :title="$p['title'] ?? 'Товар'"
                :price="$p['price'] ?? '0.00'"
                :price_no_sale="$p['price_no_sale'] ?? '0.00'"
                :image="$p['image'] ?? '/images/no-image.svg'"
            />
        @empty
            @for ($i=0; $i<6; $i++)
                <x-product.card />
            @endfor
        @endforelse
    </div>
</section>
