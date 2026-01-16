@extends('layouts.app')

@section('title', $product['seo_title'] ?? $product['seo_title'])
 @php

 @endphp
@section('content')
    @php
        // данные для селектора
        $rows            = $product['variant_rows'];
        $characteristics = $product['characteristics'];
        $rootId          = $product['root_id'];
        $price           = $product['price'];
        $price_no_sale   = $product['old_price'];

        // подготовка стора
        $rootId  = $rootId ?? ($rows[0]['product_id'] ?? null);
        $rootKey = $rootId !== null ? (string)$rootId : '';
        $priceMap = [];
        foreach ($rows as $row) {
            $priceMap[(string)$row['product_id']] = [
                'price' => (float)($row['price'] ?? 0),
                'old'   => isset($row['old_price']) ? (float)$row['old_price'] : null,
            ];
        }
        $defaultPrice = (float)($product['price'] ?? 0);
        
        // расчет начальной скидки для начального варианта
        $initialDiscount = null;
        if ($rootKey && isset($priceMap[$rootKey])) {
            $initialPriceData = $priceMap[$rootKey];
            $oldPrice = $initialPriceData['old'];
            $currentPrice = $initialPriceData['price'];
            if ($oldPrice !== null && $oldPrice > 0 && $currentPrice > 0 && $oldPrice > $currentPrice) {
                $initialDiscount = round((($oldPrice - $currentPrice) / $oldPrice) * 100);
            }
        }
    @endphp

    {{-- ИНИЦИАЛИЗАЦИЯ ОБЩЕГО STORE ДЛЯ ЦЕН --}}
    <div
        x-data
        x-init="
            Alpine.store('sku', {
                selected: '{{ $rootKey }}',
                prices: @js($priceMap),
                fmt(v){ const n=Number(v||0); const parts=n.toFixed(2).split('.'); return { uah: parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,' '), kop: parts[1] }; },
                price(){ const p=this.prices[this.selected]; return p?.price ?? {{ $defaultPrice }}; },
                old(){ const p=this.prices[this.selected]; return (p?.old && p.old > (p?.price ?? 0)) ? p.old : null; },
                discountPercent(){ 
                    const oldPrice = this.old(); 
                    const currentPrice = this.price(); 
                    if (oldPrice && oldPrice > 0 && currentPrice > 0 && oldPrice > currentPrice) {
                        return Math.round(((oldPrice - currentPrice) / oldPrice) * 100);
                    }
                    return null;
                },
            })
        "
    >

        <div class="mx-auto desk:w-[1198px] w-[357px] md:w-[736px] max-w-full">

            {{-- Хлебные крошки --}}
            <nav class="text-sm text-gray-500 my-4">
                <a href="{{ route('home') }}" class="hover:text-gray-700">{{ st('menu.home','Головна') }}</a>
                <span class="mx-2">→</span>
                <a href="{{ url('/' . ($category->slug ?? '')) }}" class="hover:text-gray-700">
                    {{ $category->title ?? $category->name ?? 'Категорія' }}
                </a>
                <span class="mx-2">→</span>
                <span class="text-gray-700">{{ $product['title'] }}</span>
            </nav>

            {{-- Верх: фото + инфо --}}
            <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                {{-- Фото --}}
                <div>
                    <div class="relative w-full rounded-xl overflow-hidden bg-gray-50">
                        <div class="aspect-[4/3] lg:aspect-[16/12]">
                            <img src="{{ $product['main_image'] }}" alt="{{ $product['title'] }}"
                                 class="h-full w-full object-cover" loading="eager">
                        </div>
                        @php
                            $showBadge = $initialDiscount !== null && $initialDiscount > 0;
                        @endphp
                        @if($showBadge)
                            <span 
                                x-show="$store.sku.discountPercent() !== null && $store.sku.discountPercent() > 0"
                                x-text="'Знижка –' + $store.sku.discountPercent() + '%'"
                                style="display: block;"
                                class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C] px-[10px] py-[4px] text-white font-intro font-bold text-[14px] leading-[16px] z-10">
                                Знижка –{{ $initialDiscount }}%
                            </span>
                        @else
                            <span 
                                x-show="$store.sku.discountPercent() !== null && $store.sku.discountPercent() > 0"
                                x-text="'Знижка –' + $store.sku.discountPercent() + '%'"
                                x-cloak
                                class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C] px-[10px] py-[4px] text-white font-intro font-bold text-[14px] leading-[16px] z-10">
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Информация / покупка --}}
                <div>
                    <h1 class="text-[#19191A] text-3xl md:text-4xl xl:text-[40px] leading-[100%] font-bold">
                        {{ $product['title'] }}
                    </h1>

                    {{-- ЦЕНА (теперь живёт тут, берётся из store) + КНОПКА --}}
                    <div class="mt-4 grid md:grid-cols-2 grid-cols-1 items-end gap-4">
                        <div>
                            {{-- старая цена --}}
                            <div class="text-[#9E9E9E] line-through" x-show="$store.sku.old()" x-cloak>
                                <span class="text-[22px] leading-[22px]" x-text="$store.sku.fmt($store.sku.old()).uah"></span>
                                <span class="relative -top-1 text-[12px] leading-[12px] ml-1"
                                      x-text="$store.sku.fmt($store.sku.old()).kop"></span>
                                <span class="text-[14px] leading-[14px] ml-1">грн</span>
                            </div>

                            {{-- текущая цена --}}
                            <div class="text-[#FF7500] font-semibold">
                                <span class="text-[28px] leading-[32px]" x-text="$store.sku.fmt($store.sku.price()).uah"></span>
                                <span class="relative -top-2 text-[14px] leading-[14px] ml-1"
                                      x-text="$store.sku.fmt($store.sku.price()).kop"></span>
                                <span class="text-sm font-medium ml-1">грн</span>
                            </div>
                        </div>

                        <div class="text-end" x-data="{ adding: false }">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 w-full md:w-[218px] justify-center bg-[#FF7500] hover:bg-orange-600 text-white font-semibold px-5 py-3 rounded-lg transition disabled:opacity-60"
                                :disabled="adding"
                                @click="
                                    adding = true;
                                    const pid = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    const price = typeof $store.sku?.price === 'function'
                                        ? Number($store.sku.price() || 0)
                                        : null;
                                    window.CartAPI.add('{{ route('cart.add') }}', {
                                        product_id: pid,
                                        qty: 1,
                                        price: price,
                                    })
                                    .then((data) => {
                                        // Cart added successfully
                                    })
                                    .catch((e) => {
                                        console.error('Product page: CartAPI.add error', e);
                                        alert('Не вдалося додати до кошика');
                                    })
                                    .finally(() => {
                                        adding = false;
                                    });
                                "
                            >
                                <x-icons.cart class="h-5 w-5" /> {{ st('product.addcart','Додати в кошик') }}
                            </button>
                        </div>
                    </div>


                    {{-- бонусы --}}
                    <div class="mt-4 flex items-start text-[#A9A9A9] text-base">
                        <img src="{{ asset('images/svg/bonus.svg') }}" alt="" class="w-[22px] h-[19px] mr-2" aria-hidden="true">
                        <span>За покупку вам будет начислено</span><span class="text-[#FF7500]">&nbsp;15&nbsp;</span><span>баллов</span>
                    </div>

                    {{-- Состав --}}
                    @if($product['description'])
                        <div class="mt-6">
                            <div class="text-[#666666] text-lg font-semibold mb-2">{{ st('product.ingredients','Склад') }}:</div>
                            <div class="prose prose-sm max-w-none text-base text-[#A9A9A9]">
                                {{ strip_tags($product['description']) }}
                            </div>
                        </div>
                    @endif

                    {{-- Кнопки-«пилюли» (варианты). Компонент БЕЗ цен, только переключает $store.sku.selected --}}
                    @if(!empty($rows))
                        <x-ui.rows-selector-detail
                            :rows="$rows"
                            :characteristics="$characteristics"
                            :root-id="$rootId"
                            store="sku"
                            :init-store="false"
                        />
                    @endif
                </div>
            </section>


        {{-- Рекомендации --}}
            <section class="mt-[40px] md:mt-8 xl:mt-[80px] recom-swiper" x-data x-init="
  new Swiper($refs.sw, {
    slidesPerView: 1,
    spaceBetween: 16,
    speed: 500,
    navigation: { nextEl: $refs.next, prevEl: $refs.prev },
    breakpoints: { 768:{slidesPerView:2}, 1024:{slidesPerView:3} },
  });
">
                <h2 class="text-[26px] font-bold text-[#FF7500] mb-4">
                    {{ st('product.recommend','Рекомендуємо спробувати') }}
                </h2>

                <div class="swiper" x-ref="sw">
                    <div class="swiper-wrapper">
                        @foreach($related as $p)
                            <div class="swiper-slide">
                                <div class="card-fixed">
                                    <x-product.card
                                        :title="$p['title'] ?? 'Товар'"
                                        :url="$p['url'] ?? ''"
                                        :article="$p['article'] ?? '12345'"
                                        :price="$p['price'] ?? '0.00'"
                                        :description="$p['description'] ?? ''"
                                        :price_no_sale="$p['price_no_sale'] ?? '0.00'"
                                        :image="$p['main_image'] ?? '/images/no-image.svg'"
                                        :characteristics="$p['characteristics'] ?? []"
                                        :rows="$p['variant_rows'] ?? []"
                                        :root_id="$p['root_id'] ?? null"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- стрелки снизу --}}
                <div class="flex justify-center items-center gap-3 mt-6 mb-2">
                    <button x-ref="prev"
                            class="swiper-prev w-[34px] h-[34px] rounded-xl bg-[#FF7500] hover:bg-orange-600 text-white flex items-center justify-center transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/>
                        </svg>
                    </button>

                    <button x-ref="next"
                            class="swiper-next w-[34px] h-[34px] rounded-xl bg-[#FF7500] hover:bg-orange-600 text-white flex items-center justify-center transition">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M8.59 16.59L10 18l6-6-6-6-1.41 1.41L13.17 12z"/>
                        </svg>
                    </button>
                </div>
            </section>
        </div>
    @include('pages.catalog.reviews', ['product' => $rootId])
@endsection
