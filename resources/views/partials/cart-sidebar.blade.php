@php
    use Illuminate\Support\Facades\Route;

    $checkoutUrl = Route::has('checkout') ? route('checkout') : (Route::has('cart.page') ? route('cart.page') : url('/cart'));
    $cartUrl     = Route::has('cart.page') ? route('cart.page') : url('/cart');

    $addUrl    = Route::has('cart.add')    ? route('cart.add')    : url('/cart/add');
    $removeUrl = Route::has('cart.remove') ? route('cart.remove') : url('/cart/remove');
@endphp

@if (empty($items))
    <div data-cart-empty class="p-6 text-center text-gray-500">
        {{ st('cart.empty', 'Кошик порожній') }}

    </div>
@else
    <div x-data="cartActions('{{ $addUrl }}','{{ $removeUrl }}')">
        <div data-cart-list>
            @foreach ($items as $it)
                @php
                    $pid   = (int) data_get($it, 'product_id');
                    $img   = data_get($it, 'image', asset('images/noimg.png'));
                    $name  = data_get($it, 'name', st('cart.item.default_name', 'Товар'));
                    $sku   = data_get($it, 'sku');
                    $code2 = data_get($it, 'code2') ?: $sku;
                    $var   = data_get($it, 'variant');
                    $q     = (int) data_get($it, 'qty', 1);
                    $p     = (float) data_get($it, 'price', 0);
                    $sum   = (float) (data_get($it, 'subtotal') ?? ($q * $p));
                @endphp

                <div
                    class="max-w-[753px] bg-white rounded-xl shadow-[0_8px_20px_rgba(0,0,0,0.05)] mb-4 p-4 sm:p-6 flex flex-wrap sm:flex-nowrap items-center justify-between gap-4"
                    data-cart-item="{{ $pid }}"
                >
                    {{-- Левая часть --}}
                    <div class="flex items-start gap-4 w-full sm:w-auto">
                        <img src="{{ $img }}" alt="" class="w-[100px] h-[80px] sm:w-[127px] sm:h-[102px] rounded-lg object-cover">
                        <div class="flex flex-col justify-between min-w-0">
                            <div class="text-[14px] leading-[16px] font-bold text-[#19191A]
                                [display:-webkit-box] [-webkit-line-clamp:2] [-webkit-box-orient:vertical] overflow-hidden">
                                {{ $name }}
                            </div>

                            @if($code2)
                                <div class="text-[13px] leading-[16px] text-[#C04103] mt-1">
                                    {{ st('cart.item.sku_label', 'Артикул:') }} {{ $code2 }}
                                </div>
                            @endif

                            @if($var)
                                <div class="text-xs text-gray-500 mt-1">{{ $var }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- Правая часть --}}
                    <div class="w-full sm:w-auto flex items-center sm:items-center gap-4 sm:gap-6
                        justify-between sm:justify-end">
                        {{-- количество --}}
                        <div class="flex items-center gap-2 order-1">
                            <button
                                class="w-6 h-6 flex items-center justify-center rounded-full bg-[#FF7500] text-white text-[16px] leading-none"
                                @click="dec({{ $pid }})">−</button>

                            <input
                                class="w-14 h-10 text-center border border-[#FF7500] rounded text-[16px] font-medium outline-none bg-white"
                                type="text" inputmode="numeric" pattern="\d*"
                                value="{{ $q }}"
                                data-cart-qty-input
                                @input.debounce.350ms="onQtyInput({{ $pid }}, $event.target)"
                                @blur="onQtyBlur({{ $pid }}, $event.target)"
                                @keydown.enter.prevent="$event.target.blur()"
                            />

                            <button
                                class="w-6 h-6 flex items-center justify-center rounded-full bg-[#FF7500] text-white text-[16px] leading-none"
                                @click="inc({{ $pid }})">+</button>
                        </div>

                        {{-- сумма --}}
                        <div class="order-2 ml-auto sm:ml-0 text-right">
                            <div class="flex items-baseline gap-1 text-[#DC2626] font-bold">
                                <span class="text-[18px]" data-cart-line-total>
                                    {{ number_format($sum, 0, ',', ' ') }}
                                </span>
                                <span class="text-[14px]">
                                    {{ st('cart.summary.currency_short', 'грн') }}
                                </span>
                            </div>
                        </div>

                        {{-- удалить --}}
                        <button
                            class="order-3 w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100"
                            title="{{ st('cart.item.delete', 'Видалити') }}"
                            @click.prevent="del({{ $pid }})"
                        >
                            {{-- svg как есть --}}
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="0.5" y="0.5" width="31" height="31" rx="3.5" fill="white"/>
                                <rect x="0.5" y="0.5" width="31" height="31" rx="3.5" stroke="#E5E7EB"/>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M9.29289 9.29289C9.68342 8.90237 10.3166 8.90237 10.7071 9.29289L16 14.5858L21.2929 9.29289C21.6834 8.90237 22.3166 8.90237 22.7071 9.29289C23.0976 9.68342 23.0976 10.3166 22.7071 10.7071L17.4142 16L22.7071 21.2929C23.0976 21.6834 23.0976 22.3166 22.7071 22.7071C22.3166 23.0976 21.6834 23.0976 21.2929 22.7071L16 17.4142L10.7071 22.7071C10.3166 23.0976 9.68342 23.0976 9.29289 22.7071C8.90237 22.3166 8.90237 21.6834 9.29289 21.2929L14.5858 16L9.29289 10.7071C8.90237 10.3166 8.90237 9.68342 9.29289 9.29289Z" fill="#929292"/>
                            </svg>
                        </button>
                    </div>
                </div>

            @endforeach
        </div>
    </div>

    <div class="p-4 border-t flex items-center justify-between text-[#19191A] text-2xl font-bold">
        <div>{{ st('cart.summary.total_to_pay', 'До сплати') }}</div>
        <div data-cart-total>
            {{ number_format($total ?? 0, 0, ',', ' ') }}
            {{ st('cart.summary.currency_short', 'грн') }}
        </div>
    </div>

    <div class="p-4 pt-0">
        <a href="{{ $checkoutUrl }}"
           class="block w-full text-center bg-orange-500 text-white py-2 rounded-lg hover:bg-orange-600 transition">
            {{ st('cart.actions.checkout', 'Оформити замовлення') }}
        </a>
        <a href="{{ $cartUrl }}" class="block w-full text-center text-gray-600 mt-2 underline hover:no-underline">
            {{ st('cart.actions.goto_cart', 'Перейти в кошик') }}
        </a>
    </div>
@endif
