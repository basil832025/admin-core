@section('page', 'checkout')
@extends('layouts.app')
@section('title','Мій заказ')

@php
    $locale = app()->getLocale();
    $isLocalized = in_array($locale, ['ru', 'en'], true);
    $routePrefix = $isLocalized ? 'localized.' : '';
    $routeParams = $isLocalized ? ['locale' => $locale] : [];

    $addUrl    = route($routePrefix . 'cart.add', $routeParams);
    $removeUrl = route($routePrefix . 'cart.remove', $routeParams);
    $checkoutSubmitUrl = route($routePrefix . 'checkout.submit', $routeParams);
    $checkPromoUrl = route($routePrefix . 'checkout.check-promo-conditions', $routeParams);
    $saveFormUrl = route($routePrefix . 'checkout.save-form-data', $routeParams, false);
    $client    = auth()->user();
    // Показываем все сохранённые адреса; если координат нет, они будут дозапрошены и сохранены при выборе
    $addresses = $client ? $client->addresses()->orderByDesc('id')->get() : collect();

    // Загружаем данные из сессии
    $sessionData = $sessionData ?? session('checkout.form_data', []);

    // Определяем выбранный адрес и способ получения
    $selectedId = old('selected_address_id', $sessionData['selected_address_id'] ?? null) ?: ($addresses->first()->id ?? null);

    // Если у клиента НЕТ сохранённых адресов — всегда показываем форму нового адреса
    if (! $client || $addresses->count() === 0) {
        $selectedId    = null;
        $useNewInitial = true;
    } else {
        // Базовое значение из старых данных / сессии (если пользователь явно выбирал «новый адрес»)
        $useNewInitial = (bool) old('use_new_address', $sessionData['use_new_address'] ?? false);

        // Если у нас уже есть выбранный сохранённый адрес — по умолчанию НЕ открываем форму нового адреса
        if ($selectedId && $addresses->contains('id', $selectedId)) {
            $useNewInitial = false;
        }
    }
    $shippingMethod = old('shipping_method', $sessionData['shipping_method'] ?? 'delivery');
    $deliveryMode = old('delivery_mode', $sessionData['delivery_mode'] ?? 'asap');
    $paymentMethod = old('payment_method', $sessionData['payment_method'] ?? 'liqpay');
@endphp

@section('content')
    <div class="mx-auto desk:w-[1208px] px-4  md:p-6 max-w-full">
        <h1 class="checkout-section-title mb-4 md:mb-6">{{ st('cart.miy-zakaz', 'Мій заказ') }}</h1>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('checkoutForm', () => ({
        method: @json($shippingMethod),
        useNew: @json($useNewInitial),
        deliveryMode: @json($deliveryMode),
        paymentMethod: @json($paymentMethod),
        init() {
            // Загружаем данные из сессии при инициализации после небольшой задержки
            this.$nextTick(() => {
                const sessionData = @json($sessionData ?? []);
                if (sessionData && Object.keys(sessionData).length > 0) {
                    try {
                        // Обновляем способ получения
                        if (sessionData.shipping_method) {
                            this.method = sessionData.shipping_method;
                        }
                        // Обновляем режим доставки
                        if (sessionData.delivery_mode) {
                            this.deliveryMode = sessionData.delivery_mode;
                        }
                        // Обновляем способ оплаты
                        if (sessionData.payment_method) {
                            this.paymentMethod = sessionData.payment_method;
                            const paymentValue = String(sessionData.payment_method || '').split('"').join('&quot;');
                            const radio = document.querySelector('[name="payment_method"][value="' + paymentValue + '"]');
                            if (radio) radio.checked = true;
                        }
                    } catch (e) {
                        console.error('Error loading session data:', e);
                    }
                }
            });
        }
    }));
});
</script>
@endpush

        <form action="{{ $checkoutSubmitUrl }}"
              method="POST" class="space-y-6" data-checkout-form novalidate
              data-check-promo-url="{{ $checkPromoUrl }}">
            @csrf

            <div
                x-data="checkoutForm"
                class="mb-6"
            >
                {{-- Переключатель способа получения + hidden
                --}}
                <div id="blk-toggle" class="mt-4 md:mt-6">
                    @include('checkout.partials._shipping-toggle')
                </div>
                {{-- Весь блок внутри страницы "Мій заказ" --}}
                <div class="mt-4 md:mt-6 flex flex-col lg:flex-row justify-center gap-4 md:gap-6 lg:gap-[32px]">

                    {{-- Левая колонка (форма) --}}
                    <div class="w-full lg:w-[580px] space-y-4 md:space-y-6" id="col-left">

                        <div id="blk-contact">
                            @include('checkout.partials._contact', ['sessionData' => $sessionData])
                        </div>

                        <div id="blk-address">
                            @include('checkout.partials._delivery-address', [
                                'sessionData' => $sessionData,
                                'useNewInitial' => $useNewInitial,
                                'selectedId' => $selectedId
                            ])
                            @include('checkout.partials._pickup-locations')
                        </div>

                        <div id="blk-extras">
                            @include('checkout.partials._extras', ['sessionData' => $sessionData])
                        </div>

                        <div id="blk-conditions">
                            @include('checkout.partials._delivery-conditions', [
                                'sessionData' => $sessionData,
                                'deliveryMode' => $deliveryMode,
                                'timeIntervals' => $timeIntervals ?? []
                            ])
                        </div>

                        {{-- Акции --}}
                        <div id="blk-promotions">
                            @include('checkout.partials._promotions')
                        </div>

                        {{-- Способ оплаты --}}
                        <div id="blk-pay">
                            @include('checkout.partials._payment-methods', [
                                'sessionData' => $sessionData,
                                'paymentMethod' => $paymentMethod
                            ])
                        </div>

                    </div>

                    {{-- Правая колонка (корзина+итоги) --}}
                    <div class="w-full lg:w-[585px] space-y-4 md:space-y-6" id="col-right">

                        <div id="blk-items">
                            @include('checkout.partials._order-items')
                        </div>

                        {{-- Промокод --}}
                        <div id="blk-promocode">
                            @include('checkout.partials._summary-promo')
                        </div>

                        {{-- Бонусы --}}
                        <div id="blk-bonus">
                            @include('checkout.partials._summary-bonus')
                        </div>

                        {{-- Сумма --}}
                        <div id="blk-totals">
                            @include('checkout.partials._summary-totals')
                        </div>

                        {{-- Согласие + кнопка --}}
                        <div id="blk-submit">
                            @include('checkout.partials._summary-submit')
                        </div>
                        <div id="blk-earned">
                            @include('checkout.partials._bonus-earned')
                        </div>
                    </div>

                </div>

            </div>
        </form>
        <style>
            [x-cloak] { display: none !important; }
        </style>

        <div x-data="{ showAuthModal: false, authMessage: '' }"
             x-cloak
             x-show="showAuthModal"
             x-on:show-auth-modal.window="
        authMessage = $event.detail.message || 'Щоб застосувати акцію, увійдіть або зареєструйтесь.';
           authName   = $event.detail.name  || '';
        authPhone  = $event.detail.phone || '';
        showAuthModal = true;
     "
             x-transition.opacity
             class="fixed inset-0 z-[500] flex items-center justify-center bg-black/50 backdrop-blur-sm">

            <div x-show="showAuthModal"
                 x-transition.scale.80
                 class="bg-white rounded-2xl shadow-xl p-6 w-[90%] max-w-[380px] text-center">

                <div class="text-lg font-semibold mb-3">{{ st('cart.potribna-avtoryzatsiya', 'Потрібна авторизація') }}</div>
                <div class="text-sm text-gray-700 mb-6" x-text="authMessage"></div>

                <div class="flex justify-center gap-3">
                    <button
                        type="button"
                        class="h-[40px] w-full rounded-full bg-[#FF7500] text-white
           text-[14px] font-semibold hover:bg-[#e56700] transition"
                        @click="
        // 1) закрываем эту модалку
        showAuthModal = false;

        // 2) подтягиваем имя, телефон и email из формы чекаута (если заполнены)
        const authName  = document.getElementById('contact_name')?.value || '';
        const authPhone = document.getElementById('contact_phone')?.value || '';
        const authEmail = document.getElementById('contact_email')?.value || '';

        // 3) открываем основное окно авторизации с уже подставленными данными
        $dispatch('open-auth-modal', {
            tab: 'login',
            name: authName,
            phone: authPhone,
            email: authEmail,
        });
    "
                    >
                        <span>{{ st('auth.login','Увійти') }}</span>
                    </button>




                    <button type="button"
                            @click="showAuthModal = false"
                            class="px-4 py-2 rounded-lg bg-gray-200 text-gray-700 hover:bg-gray-300">
                        {{ st('all.skasuvaty','Скасувати') }}
                    </button>
                </div>
            </div>
        </div>


    </div>
@endsection
@php
    $zones = \App\Models\DeliveryZone::where('is_active', true)
        ->orderBy('sort_order')
        ->get()
        ->keyBy('name')
        ->map(function($zone) {
            return [
                'name' => $zone->name,
                'color' => $zone->color,
                'delivery_price' => (float)$zone->delivery_price,
                'delivery_time_min' => (int)$zone->delivery_time_min,
                'delivery_time_max' => (int)$zone->delivery_time_max,
                'free_delivery_from' => (float)$zone->free_delivery_from,
            ];
        });
@endphp

@push('scripts')
    <script>
        window.DELIVERY_ZONES = @json($zones);
    </script>

    <script>
        (function() {
            window.__googleMapsLoading = true;
            window.__googleMapsLoaded = false;
            window.__onGoogleMapsLoaded = function() {
                window.__googleMapsLoaded = true;
                window.__googleMapsLoading = false;
            };

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places,geometry&callback=__onGoogleMapsLoaded';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        })();
    </script>

    @vite(['resources/js/map-cart.js'])
    @push('scripts')
        <script>
            // Используем относительный URL, чтобы на клон‑сайтах (test-домены, другие хостинги)
            // запрос шёл на тот же домен, с которого открыт checkout.
            window.CHECKOUT_CONFIG = {
                csrf: @json(csrf_token()),
                // route(..., [], false) — путь без домена, например "/checkout/save-form-data"
                saveUrl: @json($saveFormUrl),
                googleMapsKey: @json(config('services.google_maps.key')),
                scheduleV2: @json($scheduleV2 ?? ['enabled' => false]),
            };
        </script>
    @endpush

@endpush
