@props([
'title' => 'Товар',
'url' => '',
'price' => '0.00',
'description' => '',
'article' => null,

'productId' => null,     // может прийти из презентера (id базового продукта)
'root_id'   => null,     // если презентер отдаёт root_id
'isFavorite' => false,

'price_no_sale' => null,
'image' => '/images/no-image.svg',
'characteristics' => [],
'rows' => [],
])

@php
    // ЕДИНЫЙ ID, по которому ставим/снимаем избранное
    $pid = $productId ?? $root_id ?? ($rows[0]['product_id'] ?? null);
@endphp

<article
    class="desk:w-[378px] md:w-[360px] w-[355px] h-[650px] flex flex-col rounded-[12px] bg-white desk:gap-8 md:gap-4 p-3 shadow-[0_8px_20px_rgba(0,0,0,0.05)]"
    @if($pid) data-product-id="{{ $pid }}" @endif
>
    <a href="{{ $url }}">
        <div class="relative desk:w-[354px] h-[220px] md:w-[336px] w-[331px] overflow-hidden rounded-[12px]">
            <img src="{{ $image }}" alt="{{ $title }}" class="h-full w-full object-cover">
            @php
                // Вычисляем процент скидки, если есть старая цена
                $discountPercent = null;
                if ($price_no_sale && $price_no_sale > 0 && $price && $price > 0) {
                    $oldPrice = (float)$price_no_sale;
                    $currentPrice = (float)$price;
                    if ($oldPrice > $currentPrice) {
                        $discountPercent = round((($oldPrice - $currentPrice) / $oldPrice) * 100);
                    }
                }
            @endphp
            @if($discountPercent && $discountPercent > 0)
                <span class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C] px-[10px] py-[4px] text-white font-intro font-bold text-[14px] leading-[16px]">
                    Знижка –{{ $discountPercent }}%
                </span>
            @endif
        </div>
    </a>

    <div class="pt-4 flex-1 flex flex-col">
        <div class="flex items-start justify-between">
            <h5 class="w-[312px] font-intro font-bold text-[16px] leading-[22px] text-neutral-700">
                {{ $title }}
            </h5>

            @if($pid)
                    <x-ui.favorite-button
                    :product-id="$pid"
                    :post-url="route('favorite.toggle', $pid)"
                    :active="$isFavorite"
                    color="#FF7500"
                />
            @endif
        </div>

        <p class="w-[354px] font-intro text-[13px] leading-[16px] text-[#C04103]">
            Артикул: {{ $article ?? '123456' }}
        </p>

        <div class="w-[354px] font-intro text-[13px] leading-[16px] text-[#A9A9A9] clamp-6 max-h-[96px] overflow-hidden">
            {!! $description !!}
        </div>

        @if(!empty($rows))
            <div class="mt-auto">
                <x-ui.rows-selector
                    :rows="$rows"
                    :characteristics="$characteristics"
                    :root-id="$pid"
                    :default-price="$price"
                    :default-old-price="$price_no_sale"
                    cart-text="{{ st('product.addcart','Додати в кошик') }}"
                />
            </div>
        @endif
    </div>
</article>
