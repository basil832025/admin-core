@php
    $cartUrl = Route::has('cart.page') ? route('cart.page') : url('/cart');
@endphp

<div
    class="bg-white rounded-[12px] shadow-[0_2px_10px_rgba(0,0,0,.08)] p-4 space-y-4"
    x-data="(() => {
        const base = (window.cartActions || (()=>({})))('{{ $addUrl }}','{{ $removeUrl }}');

        base.doRemove = async (payload) => {
            const id = (typeof payload === 'object') ? payload.id : payload;

            if (typeof base.remove === 'function') return base.remove({ id });
            if (typeof base.del    === 'function') return base.del(id);

            try {
                const res = await fetch('{{ $removeUrl }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ product_id: id })
                });

                const data = await res.json();

                const row = document.querySelector(`[data-cart-item='${id}']`);
                if (row) row.remove();

                if (window.Alpine && Alpine.store('cart')) {
                    Alpine.store('cart').setQty(Number(data.qty ?? 0));
                    Alpine.store('cart').setTotal(Number(data.total_price ?? data.total ?? 0));
                }

                document.dispatchEvent(new CustomEvent('cart-updated', { detail: data }));

                const fmt = (v) =>
                    new Intl.NumberFormat('uk-UA').format(Number(v || 0)) + ' {{ st('cart.summary.currency_short', 'грн.') }}';

                const newTotal = data.total_price ?? data.total ?? 0;

                const totalEl = document.querySelector('[data-cart-total]');
                if (totalEl) {
                    totalEl.textContent = fmt(newTotal);
                }

                const subEl = document.querySelector('[data-checkout-subtotal]');
                if (subEl) {
                    subEl.textContent = fmt(newTotal);
                }

                const grandEl = document.querySelector('[data-checkout-total]');
                if (grandEl) {
                    grandEl.textContent = fmt(newTotal);
                }

            } catch (e) {
                console.error('remove fallback error', e);
            }
        };

        return base;
    })()"
    @cart-remove.stop="doRemove($event.detail)"
>
    <div class="flex items-start justify-between">
        <div class="text-[28px] leading-8 font-semibold">{{ st('cart.miy-zakaz', 'Мій заказ') }}</div>

        <a href="{{ $cartUrl }}"
           class="text-[#FF7500] font-medium hover:underline">{{ st('cart.redaguvaty', 'Редагувати') }}</a>
    </div>

    <div class="space-y-4">
        @foreach($items as $it)
            @php
                $pid  = $it['product_id'];
                $qty  = (int)($it['qty'] ?? 1);
                $name = $it['name'] ?? st('cart.item.default_name', 'Товар');
                $img  = $it['image'] ?? asset('images/placeholder-4x3.jpg');
                $var   = data_get($it, 'variant');
                $price = (float)($it['subtotal'] ?? 0);
                $uah   = floor($price);
                $kop   = sprintf('%02d', (int)round(($price - $uah) * 100));

                $old   = isset($it['old_subtotal']) ? (float)$it['old_subtotal'] : null;
            @endphp

            <div class="flex items-center justify-between gap-2 border border-[#F1F2F4] h-[116px] rounded-[12px]
                        shadow-[0_2px_8px_rgba(0,0,0,.06)] p-2" data-cart-item="{{ $pid }}">
                {{-- картинка --}}
                <img src="{{ $img }}" alt="" class="w-[120px] h-[96px] rounded-[8px] object-cover shrink-0">

                {{-- инфо --}}
                <div class="flex-1 min-w-0 pr-2">
                    <div class="text-[10px] font-semibold text-[#272828] w-[155px] line-clamp-2">{{ $name }}</div>

                    @if(!empty($it['meta_line']))
                        <div class="mt-1 text-[12px] text-[#9CA3AF]">{!! $it['meta_line'] !!}</div>
                    @elseif($var)
                        <div class="mt-1 text-[12px] text-[#9CA3AF]">{{ $var }}</div>
                    @endif

                    <div class="mt-1 text-[12px] text-[#9CA3AF]">{{ $qty }} шт</div>
                </div>

                {{-- qty + цена + удалить --}}
                <div class="flex items-center gap-4 sm:gap-5 shrink-0 w-[180px] justify-end">
                    {{-- контрол количества --}}
                    <div class="inline-flex items-center bg-[#FDDDA7] text-[#FF7500] h-10 rounded-[4px] px-1 shrink-0">
                        <button type="button" class="w-6 h-6 grid place-items-center text-xl leading-none"
                                @click.prevent="dec({ id: {{ $pid }}, price: {{ (float)($it['price'] ?? 0) }} })">−</button>
                        <div class="w-8 text-center font-semibold">{{ $qty }}</div>
                        <button type="button" class="w-6 h-6 grid place-items-center text-xl leading-none"
                                @click.prevent="inc({ id: {{ $pid }}, price: {{ (float)($it['price'] ?? 0) }} })">+</button>
                    </div>

                    {{-- цена --}}
                    <div class="text-right min-w-[90px] whitespace-nowrap">
                        <div class="flex items-baseline justify-end gap-1 text-[#E44800] font-bold leading-none">
                            <span class="text-[18px] tabular-nums" data-cart-line-total>
                                {{ number_format($uah, 0, ',', ' ') }}
                            </span>
                            <sup class="align-top text-[12px] font-semibold tabular-nums">{{ $kop }}</sup>
                            <span class="text-[14px]">{{ st('cart.summary.currency_short', 'грн.') }}</span>
                        </div>

                        @if($old && $old > $price)
                            <div class="text-[14px] text-[#9CA3AF] line-through tabular-nums">
                                {{ number_format($old, 0, ',', ' ') }} {{ st('cart.summary.currency_short', 'грн') }}
                            </div>
                        @endif
                    </div>

                    {{-- удалить с кастомным confirm --}}
                    <div x-data="{ ask:false }" class="relative">
                        <button
                            type="button"
                            class="w-8 h-8 grid place-items-center border border-[#E5E7EB] rounded-[4px] text-[#9CA3AF] hover:text-[#6B7280]"
                            title="Видалити"
                            aria-label="Видалити"
                            @click="ask = true"
                        >
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                      d="M0.292893 0.292893C0.683417 -0.0976309 1.31658 -0.0976309 1.70711 0.292893L7 5.58579L12.2929 0.292893C12.6834 -0.0976311 13.3166 -0.0976311 13.7071 0.292893C14.0976 0.683417 14.0976 1.31658 13.7071 1.70711L8.41421 7L13.7071 12.2929C14.0976 12.6834 14.0976 13.3166 13.7071 13.7071C13.3166 14.0976 12.6834 14.0976 12.2929 13.7071L7 8.41421L1.70711 13.7071C1.31658 14.0976 0.683418 14.0976 0.292893 13.7071C-0.0976309 13.3166 -0.0976309 12.6834 0.292893 12.2929L5.58579 7L0.292893 1.70711C-0.0976311 1.31658 -0.0976311 0.683418 0.292893 0.292893Z"
                                      fill="#929292"/>
                            </svg>
                        </button>

                        <div
                            x-show="ask"
                            x-transition
                            @click.outside="ask = false"
                            class="absolute right-0 top-full mt-2 bg-white shadow-lg rounded-md border border-gray-200 p-3 w-[180px] z-20"
                        >
                            <div class="text-sm text-gray-800 mb-2 text-center">
                                {{ st('cart.vydalyty-tsei-tovar-iz-zamovlennya', 'Видалити цей товар із замовлення') }}?
                            </div>
                            <div class="flex justify-center gap-2">
                                <button
                                    type="button"
                                    class="px-3 py-1.5 rounded-md text-white bg-[#FF7500] hover:bg-[#e56700] text-sm font-semibold"
                                    @click="$dispatch('cart-remove', { id: {{ $pid }} }); ask = false"
                                >
                                    {{ st('cart.yes', 'Так') }}
                                </button>
                                <button
                                    type="button"
                                    class="px-3 py-1.5 rounded-md text-gray-600 bg-gray-100 hover:bg-gray-200 text-sm font-medium"
                                    @click="ask = false"
                                >
                                    {{ st('cart.no', 'Ні') }}
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach
    </div>
</div>
