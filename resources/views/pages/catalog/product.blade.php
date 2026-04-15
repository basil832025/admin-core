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
        $articleMap = [];
        foreach ($rows as $row) {
            $priceMap[(string)$row['product_id']] = [
                'price' => (float)($row['price'] ?? 0),
                'old'   => isset($row['old_price']) ? (float)$row['old_price'] : null,
            ];
            $articleMap[(string)$row['product_id']] = trim((string)($row['article'] ?? ''));
        }
        $defaultPrice = (float)($product['price'] ?? 0);
        $defaultArticle = trim((string)($product['article'] ?? ''));

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
                articles: @js($articleMap),
                bonusPercent: {{ $bonusPercent ?? 0 }},
                minOrderSumForEarn: {{ $minOrderSumForEarn ?? 0 }},
                defaultArticle: @js($defaultArticle),
                fmt(v){ const n=Number(v||0); const parts=n.toFixed(2).split('.'); return { uah: parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,' '), kop: parts[1] }; },
                price(){ const p=this.prices[this.selected]; return p?.price ?? {{ $defaultPrice }}; },
                old(){ const p=this.prices[this.selected]; return (p?.old && p.old > (p?.price ?? 0)) ? p.old : null; },
                article(){
                    const value = this.articles[this.selected];
                    if (typeof value === 'string' && value.trim() !== '') return value;
                    return this.defaultArticle;
                },
                discountPercent(){
                    const oldPrice = this.old();
                    const currentPrice = this.price();
                    if (oldPrice && oldPrice > 0 && currentPrice > 0 && oldPrice > currentPrice) {
                        return Math.round(((oldPrice - currentPrice) / oldPrice) * 100);
                    }
                    return null;
                },
                discountAmount(){
                    const oldPrice = this.old();
                    const currentPrice = this.price();
                    if (oldPrice && oldPrice > 0 && currentPrice > 0 && oldPrice > currentPrice) {
                        return oldPrice - currentPrice;
                    }
                    return 0;
                },
                bonusEarn(){
                    const currentPrice = this.price();
                    const discount = this.discountAmount();
                    const base = Math.max(currentPrice - discount, 0);
                    // Проверка минимальной суммы для начисления
                    if (this.minOrderSumForEarn > 0 && base < this.minOrderSumForEarn) {
                        return 0;
                    }
                    if (base <= 0 || this.bonusPercent <= 0) return 0;
                    // Рассчитываем бонусы (как в сервисе, округляем до 2 знаков)
                    const bonus = Math.round(base * this.bonusPercent / 100 * 100) / 100;
                    // Для отображения округляем до целого
                    return Math.round(bonus);
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

            {{-- Кнопка "Назад" --}}
            <a href="javascript:history.back()"
               class="inline-flex items-center gap-2 text-[#333333] hover:text-[#FF7500] transition mb-4">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="#FF7500" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span class="font-intro font-bold text-[18px] leading-[22px]">{{ st('common.back', 'Назад') }}</span>
            </a>

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
                            $discountLabel = st('product.badges.discount', 'Знижка');
                        @endphp
                        @if($showBadge)
                            <span
                                x-show="$store.sku.discountPercent() !== null && $store.sku.discountPercent() > 0"
                                x-text="@js($discountLabel) + ' -' + $store.sku.discountPercent() + '%'"
                                style="display: block;"
                                class="absolute right-[10px] top-[10px] rounded-[3px] bg-[#B91C1C] px-[10px] py-[4px] text-white font-intro font-bold text-[14px] leading-[16px] z-10">
                                {{ $discountLabel }} -{{ $initialDiscount }}%
                            </span>
                        @else
                            <span
                                x-show="$store.sku.discountPercent() !== null && $store.sku.discountPercent() > 0"
                                x-text="@js($discountLabel) + ' -' + $store.sku.discountPercent() + '%'"
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

                        <div class="text-end" x-data="{
                            adding: false,
                            cartQty: 0,
                            async init() {
                                // Проверяем количество в корзине при инициализации
                                await this.checkCartQty();

                                // Слушаем обновления корзины
                                window.addEventListener('cart-updated', (e) => {
                                    const selectedId = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    if (e?.detail?.item?.product_id === parseInt(selectedId)) {
                                        this.cartQty = e.detail.item?.qty ?? 0;
                                    } else if (e?.detail?.items) {
                                        const item = e.detail.items.find(i => parseInt(i.product_id) === parseInt(selectedId));
                                        if (item) {
                                            this.cartQty = item.qty ?? 0;
                                        }
                                    }
                                });

                                // Обновляем количество при смене варианта товара
                                $watch('$store.sku.selected', () => {
                                    this.checkCartQty();
                                });
                            },
                            async checkCartQty() {
                                try {
                                    const cache = window.__CART_CACHE__;
                                    let data;
                                    if (cache) {
                                        data = await cache.get();
                                    } else {
                                        const res = await fetch('{{ route('cart.info') }}', {
                                            headers: { 'Accept': 'application/json' }
                                        });
                                        data = await res.json();
                                    }
                                    const selectedId = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    const item = (data?.items ?? []).find(i => parseInt(i.product_id) === parseInt(selectedId));
                                    this.cartQty = item?.qty ?? 0;
                                } catch (e) {
                                    this.cartQty = 0;
                                }
                            },
                            async addToCart() {
                                if (this.adding) return;
                                this.adding = true;

                                try {
                                    const pid = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    const price = typeof $store.sku?.price === 'function'
                                        ? Number($store.sku.price() || 0)
                                        : null;
                                    const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                                        product_id: pid,
                                        qty: 1,
                                        price: price,
                                    });
                                    this.cartQty = data?.item?.qty ?? 1;
                                } catch (e) {
                                    console.error('Product page: CartAPI.add error', e);
                                    alert('Не вдалося додати до кошика');
                                } finally {
                                    this.adding = false;
                                }
                            },
                            async incrementQty() {
                                if (this.adding) return;
                                this.adding = true;

                                try {
                                    const pid = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    const price = typeof $store.sku?.price === 'function'
                                        ? Number($store.sku.price() || 0)
                                        : null;
                                    const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                                        product_id: pid,
                                        qty: 1,
                                        price: price,
                                    });
                                    this.cartQty = data?.item?.qty ?? this.cartQty + 1;
                                } catch (e) {
                                    console.error('Product page: increment error', e);
                                    alert('Не вдалося оновити кількість');
                                } finally {
                                    this.adding = false;
                                }
                            },
                            async decrementQty() {
                                if (this.adding || this.cartQty <= 1) return;
                                this.adding = true;

                                try {
                                    const pid = $store.sku?.selected || '{{ $rootId ?? 0 }}';
                                    const price = typeof $store.sku?.price === 'function'
                                        ? Number($store.sku.price() || 0)
                                        : null;
                                    const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                                        product_id: pid,
                                        qty: -1,
                                        price: price,
                                    });
                                    this.cartQty = data?.item?.qty ?? Math.max(0, this.cartQty - 1);
                                } catch (e) {
                                    console.error('Product page: decrement error', e);
                                    alert('Не вдалося оновити кількість');
                                } finally {
                                    this.adding = false;
                                }
                            }
                        }">
                            {{-- Кнопка "Добавить в корзину" --}}
                            <button
                                x-show="cartQty === 0"
                                x-cloak
                                type="button"
                                class="inline-flex items-center gap-2 w-full md:w-[218px] justify-center bg-[#FF7500] hover:bg-orange-600 text-white font-semibold px-5 py-3 rounded-[4px] transition disabled:opacity-60"
                                :disabled="adding"
                                @click="addToCart"
                            >
                                <template x-if="!adding">
                                    <x-icons.cart class="h-5 w-5" />
                                </template>
                                <template x-if="adding">
                                    <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                </template>
                                {{ st('product.addcart','Додати в кошик') }}
                            </button>

                            {{-- Контролы количества --}}
                            <div x-show="cartQty > 0" x-cloak class="inline-flex items-center bg-[#FDDDA7] text-[#FF7500] h-10 rounded-[4px] px-1 shrink-0">
                                <button
                                    type="button"
                                    class="w-6 h-6 grid place-items-center text-xl leading-none"
                                    @click="decrementQty"
                                    :disabled="adding || cartQty <= 1"
                                    aria-label="Зменшити кількість"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12H19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                                <div class="w-8 text-center font-semibold" x-text="cartQty">1</div>
                                <button
                                    type="button"
                                    class="w-6 h-6 grid place-items-center text-xl leading-none"
                                    @click="incrementQty"
                                    :disabled="adding"
                                    aria-label="Збільшити кількість"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12H19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                        <path d="M12 5V19" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>


                    {{-- бонусы отключены по задаче, вместо них показываем артикул --}}
                    <div class="mt-4 flex items-center gap-1 text-[#C04103] text-base">
                        <span class="whitespace-nowrap">{{ st('product.sku_label', 'Артикул') }}: <span class="text-[#C04103] font-semibold" x-text="$store.sku.article() || '123'">{{ $defaultArticle !== '' ? $defaultArticle : '123' }}</span></span>
                    </div>

                    {{-- Состав --}}
                    @php
                        $ingredientsSource = trim((string) ($product['ingredients_text'] ?? ''));

                        if ($ingredientsSource === '') {
                            $ingredientsSource = trim((string) ($product['short_desc'] ?? ''));
                        }

                        if ($ingredientsSource === '') {
                            $ingredientsSource = (string) ($product['description'] ?? '');
                        }

                        $ingredientsText = preg_replace('/\s+/u', ' ', strip_tags($ingredientsSource));
                        $ingredientsText = trim((string) $ingredientsText);

                    @endphp
                    @if($ingredientsText !== '')
                        <div class="mt-6">
                            <div class="text-[#666666] text-lg font-semibold mb-2">{{ st('product.ingredients','Склад') }}:</div>
                            <div class="prose prose-sm max-w-none text-base text-[#A9A9A9]">
                                {{ $ingredientsText }}
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
    slidesPerView: 1.06,
    spaceBetween: 12,
    speed: 360,
    watchOverflow: true,
    grabCursor: true,
    threshold: 4,
    resistanceRatio: 0.65,
    longSwipesRatio: 0.15,
    longSwipesMs: 220,
    touchReleaseOnEdges: true,
    touchStartPreventDefault: false,
    navigation: { nextEl: $refs.next, prevEl: $refs.prev },
    breakpoints: {
      768: { slidesPerView: 2, spaceBetween: 16, speed: 500, resistanceRatio: 0.85 },
      1024: { slidesPerView: 3, spaceBetween: 16, speed: 500 },
    },
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
                                        :description="$p['card_description'] ?? ($p['description'] ?? '')"
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
                <div class="hidden md:flex justify-center items-center gap-3 mt-6 mb-2">
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
