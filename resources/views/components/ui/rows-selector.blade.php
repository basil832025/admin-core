@props([
'rows'            => [],
'characteristics' => [],          // [{id, slug, title, svg, sort}]
'rootId'          => null,
'defaultPrice'    => 0,
'defaultOldPrice' => null,
'cartText'        => 'Додати в кошик',
'personSlug'      => 'persons',
])

@php
    $fmt = function ($val) {
        [$uah, $kop] = explode(',', number_format((float)$val, 2, ',', ' '));
        return ['uah' => $uah, 'kop' => $kop];
    };

    $rootId   = $rootId ?? ($rows[0]['product_id'] ?? null);
    $rootKey  = $rootId !== null ? (string)$rootId : '';
    $rootRow  = null;
    if ($rootId) foreach ($rows as $r) if (($r['product_id'] ?? null) === $rootId) { $rootRow = $r; break; }
    $rootRow ??= $rows[0] ?? ['price'=>$defaultPrice,'old_price'=>$defaultOldPrice,'product_id'=>null];

    // Для одного варианта уменьшаем отступы
    $rowsCount = count($rows ?? []);
    $isSingleVariant = $rowsCount <= 1;

    $p  = $fmt($rootRow['price'] ?? $defaultPrice);
    $op = ($rootRow['old_price'] ?? null) && ($rootRow['old_price'] > ($rootRow['price'] ?? 0)) ? $fmt($rootRow['old_price']) : null;

    $priceMap = [];
    foreach ($rows as $row) {
        $priceMap[(string)$row['product_id']] = [
            'price' => (float)($row['price'] ?? 0),
            'old'   => isset($row['old_price']) ? (float)$row['old_price'] : null,
        ];
    }

    // ********** ВАЖНЫЙ ПАТЧ: persons ВСЕГДА СПРАВА **********
    $chars = collect($characteristics)->sortBy('sort')->values();

    $personsIdx = $chars->search(fn($c) => ($c['slug'] ?? null) === $personSlug);
    if ($personsIdx !== false) {
        $rightChar = $chars[$personsIdx];
        // слева берём любые другие 1–2 характеристики, исключая persons
        $leftChars = $chars->filter(fn($c, $i) => $i !== $personsIdx)->take(2)->values();
    } else {
        // как раньше: 1–2 слева, 3-я справа (если persons нет в наборе)
        $leftChars = $chars->slice(0, 2)->values();
        $rightChar = $chars->get(2);
    }
    // *********************************************************
@endphp

@if(!empty($rows))
    <div
        x-data="{
        selected: @js($rootKey),
        prices:   @js($priceMap),

        fmt(v){
            const n = Number(v||0);
            const parts = n.toFixed(2).split('.');
            return { uah: parts[0].replace(/\B(?=(\d{3})+(?!\d))/g,' '), kop: parts[1] };
        },

        // состояние кнопки
        adding: false,
        cartQty: 0,

        init() {
            // Отправляем событие при изменении selected
            this.$watch('selected', (newVal) => {
                const detail = {
                    productId: newVal,
                    price: this.prices[newVal]?.price ?? null,
                    oldPrice: this.prices[newVal]?.old ?? null,
                };
                
                // Отправляем событие на родительский элемент (article)
                const parentCard = this.$el.closest('article[x-data]');
                if (parentCard && window.Alpine) {
                    const cardData = window.Alpine.$data(parentCard);
                    if (cardData && cardData.handleVariantSelected) {
                        cardData.handleVariantSelected({ detail });
                    }
                }
                
                // Также отправляем всплывающее событие для надежности
                this.$dispatch('variant-selected', detail);
                
                // При смене варианта проверяем количество в корзине
                this.checkCartQty();
            });
            
            // Отправляем начальное событие после инициализации
            // Откладываем checkCartQty на небольшую задержку, чтобы кеш успел загрузиться
            this.$nextTick(() => {
                const detail = {
                    productId: this.selected,
                    price: this.prices[this.selected]?.price ?? null,
                    oldPrice: this.prices[this.selected]?.old ?? null,
                };
                
                const parentCard = this.$el.closest('article[x-data]');
                if (parentCard && window.Alpine) {
                    const cardData = window.Alpine.$data(parentCard);
                    if (cardData && cardData.handleVariantSelected) {
                        cardData.handleVariantSelected({ detail });
                    }
                }
                
                this.$dispatch('variant-selected', detail);
                
                // Откладываем проверку корзины, чтобы все карточки не запрашивали одновременно
                // Используем небольшую случайную задержку для батчинга запросов
                setTimeout(() => {
                    this.checkCartQty();
                }, Math.random() * 50 + 10); // 10-60ms случайная задержка
            });
            
            // Слушаем обновления корзины
            window.addEventListener('cart-updated', (e) => {
                if (e?.detail?.item?.product_id === parseInt(this.selected)) {
                    this.cartQty = e.detail.item?.qty ?? 0;
                } else if (e?.detail?.items) {
                    // Если пришли все items, обновляем для текущего товара
                    const item = e.detail.items.find(i => parseInt(i.product_id) === parseInt(this.selected));
                    if (item) {
                        this.cartQty = item.qty ?? 0;
                    }
                }
            });
        },

        async checkCartQty() {
            try {
                // Используем глобальный кеш вместо прямого запроса
                const cache = window.__CART_CACHE__;
                if (!cache) {
                    // Fallback на прямой запрос, если кеш не инициализирован
                    const res = await fetch('{{ route('cart.info') }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    const item = (data?.items ?? []).find(i => parseInt(i.product_id) === parseInt(this.selected));
                    this.cartQty = item?.qty ?? 0;
                    return;
                }
                
                const data = await cache.get();
                const item = (data?.items ?? []).find(i => parseInt(i.product_id) === parseInt(this.selected));
                this.cartQty = item?.qty ?? 0;
            } catch (e) {
                this.cartQty = 0;
            }
        },

        // ✅ обновлённый метод добавления в корзину
        async addToCart() {
            if (this.adding) return;
            this.adding = true;

            try {
                const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                    product_id: this.selected,
                    qty: 1,
                    price: this.prices[this.selected]?.price ?? null,
                });

                // Обновляем количество в корзине
                this.cartQty = data?.item?.qty ?? 1;

                // уведомление (опционально)
                this.$dispatch('notify', { text: 'Додано до кошика', type: 'success' });

            } catch (e) {
                console.error(e);
                alert('Не вдалося додати до кошика');
            } finally {
                this.adding = false;
            }
        },

        async incrementQty() {
            if (this.adding) return;
            this.adding = true;

            try {
                const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                    product_id: this.selected,
                    qty: 1,
                    price: this.prices[this.selected]?.price ?? null,
                });

                this.cartQty = data?.item?.qty ?? this.cartQty + 1;

            } catch (e) {
                console.error(e);
                alert('Не вдалося оновити кількість');
            } finally {
                this.adding = false;
            }
        },

        async decrementQty() {
            if (this.adding || this.cartQty <= 1) return;
            this.adding = true;

            try {
                const data = await window.CartAPI.add('{{ route('cart.add') }}', {
                    product_id: this.selected,
                    qty: -1,
                    price: this.prices[this.selected]?.price ?? null,
                });

                this.cartQty = data?.item?.qty ?? Math.max(0, this.cartQty - 1);

            } catch (e) {
                console.error(e);
                alert('Не вдалося оновити кількість');
            } finally {
                this.adding = false;
            }
        },
    }"
        class="{{ $isSingleVariant ? 'mt-1' : 'mt-3' }} text-[13px]"
    >

    @foreach ($rows as $r)
            <x-ui.choice-row :value="(string) $r['product_id']">
                <x-slot:left>
                    @foreach($leftChars as $i => $char)
                        @php
                            $val = $r['char_values'][$char['id']] ?? null;
                            $svg = $char['svg'] ?? null;
                        @endphp
                        <span class="inline-flex items-center gap-2 {{ $i ? 'ml-6 md:ml-8' : '' }}">
                        @if($svg)
                                <span aria-hidden="true" class="inline-block h-5 w-5"
                                      style="background-color: currentColor;
                                          mask-image:url('{{ $svg }}');-webkit-mask-image:url('{{ $svg }}');
                                          mask-repeat:no-repeat;-webkit-mask-repeat:no-repeat;
                                          mask-position:center;-webkit-mask-position:center;
                                          mask-size:contain;-webkit-mask-size:contain;"></span>
                            @endif
                            @if($val)<span>{{ $val }}</span>@endif
                    </span>
                    @endforeach
                </x-slot:left>

                <x-slot:right>
                    @if($rightChar)
                        @php
                            $valRaw    = $r['char_values'][$rightChar['id']] ?? null;
                            $val       = is_array($valRaw) ? (string)($valRaw['title'] ?? reset($valRaw)) : (string)$valRaw;

                            $isPersons = ($rightChar['slug'] ?? null) === $personSlug;
                            $people    = 1;
                            if ($isPersons) {
                                $digits = (int) preg_replace('/\D+/', '', (string)$val);
                                $people = max(1, $digits ?: (is_numeric($val) ? (int)$val : 1));
                            }

                            $svgRight = $rightChar['svg'] ?? null;
                            $personIcon = $svgRight
                                ? '<span aria-hidden="true" class="inline-block h-5 w-[10px]"
                                    style="background-color: currentColor;
                                    mask-image:url(\'' . e($svgRight) . '\'); -webkit-mask-image:url(\'' . e($svgRight) . '\');
                                    mask-repeat:no-repeat; -webkit-mask-repeat:no-repeat;
                                    mask-position:center; -webkit-mask-position:center;
                                    mask-size:contain; -webkit-mask-size:contain;"></span>'
                                : '';
                        @endphp

                        {{-- Прижимаем правый блок вправо --}}
                        <span class="ml-auto inline-flex items-center whitespace-nowrap justify-end text-right shrink-0">
                        @if($isPersons)
                                @if($people <= 4)
                                    {!! str_repeat($personIcon, $people) !!}
                                @else
                                    {!! $personIcon !!}
                                    <span class="ml-1">&times;&nbsp;{{ $people }}</span>
                                @endif
                                {{-- Показываем текст для персон --}}
                                @if(!empty(trim($val)))<span class="ml-1">{{ $val }}</span>@endif
                            @else
                                {!! $personIcon !!}
                                @if($val)<span class="ml-1">{{ $val }}</span>@endif
                            @endif
                    </span>
                    @endif
                </x-slot:right>
            </x-ui.choice-row>
        @endforeach

        {{-- подвал цен + кнопка (для карточек-списка можно оставить; для детальной страницы — убрать) --}}
        <div class="{{ $isSingleVariant ? 'mt-2' : 'mt-3' }} flex items-center justify-between">
            <div class="flex items-baseline gap-1 whitespace-nowrap shrink-0">
                <div class="flex items-baseline gap-1 text-neutral-400 line-through whitespace-nowrap"
                     x-show="prices[selected]?.old && prices[selected]?.old > prices[selected]?.price">
                    <span class="font-semibold text-[16px] leading-[16px]" x-text="fmt(prices[selected]?.old).uah">{{ $op['uah'] ?? '' }}</span>
                    <span class="relative -top-2 font-bold text-[11px] leading-[11px]" x-text="fmt(prices[selected]?.old).kop">{{ $op['kop'] ?? '' }}</span>
                    <span class="text-[12px] leading-[12px]">{{ st('all.grn','грн') }}</span>
                </div>

                <div class="flex items-baseline gap-1 text-[#333333] whitespace-nowrap">
                    <span class="font-bold text-[26px] leading-[32px]" x-text="fmt(prices[selected]?.price).uah">{{ $p['uah'] }}</span>
                    <span class="relative -top-3 font-bold text-[12px] leading-[12px]" x-text="fmt(prices[selected]?.price).kop">{{ $p['kop'] }}</span>
                    <span class="text-[14px] leading-[14px]">{{ st('all.grn','грн') }}</span>
                </div>
            </div>

            {{-- Кнопка "Добавить в корзину" --}}
            <button
                type="button"
                x-show="cartQty === 0"
                x-cloak
                class="inline-flex items-center text-[12px] w-[173px] h-[36px] gap-2 rounded bg-[#FF7500] px-4 font-semibold text-white shadow-[0_4px_12px_rgba(255,117,0,.35)] transition
               hover:bg-[#ff841f] active:bg-[#e66700] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#FF7500]/50 disabled:opacity-60"
                x-bind:data-product-id="selected"
                @click="addToCart"
                x-bind:disabled="adding"
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
                {{ $cartText }}
            </button>

            {{-- Контролы количества --}}
            <div x-show="cartQty > 0" x-cloak class="inline-flex items-center bg-[#FDDDA7] text-[#FF7500] h-10 rounded-[4px] px-1 shrink-0">
                <button
                    type="button"
                    class="w-6 h-6 grid place-items-center text-xl leading-none"
                    @click="decrementQty"
                    x-bind:disabled="adding || cartQty <= 1"
                >−</button>
                <div class="w-8 text-center font-semibold" x-text="cartQty">1</div>
                <button
                    type="button"
                    class="w-6 h-6 grid place-items-center text-xl leading-none"
                    @click="incrementQty"
                    x-bind:disabled="adding"
                >+</button>
            </div>

        </div>
    </div>
@endif
