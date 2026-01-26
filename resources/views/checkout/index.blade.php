@extends('layouts.app')
@section('title','Мій заказ')

@php
    $addUrl    = route('cart.add');
    $removeUrl = route('cart.remove');
    $client    = auth()->user();
    $addresses = $client ? $client->addresses()->orderByDesc('id')->get() : collect();

    // Загружаем данные из сессии
    $sessionData = $sessionData ?? session('checkout.form_data', []);

    // Определяем выбранный адрес и способ получения
    $selectedId = old('selected_address_id', $sessionData['selected_address_id'] ?? null) ?: ($addresses->first()->id ?? null);
    $useNewInitial = old('use_new_address', $sessionData['use_new_address'] ?? ($selectedId ? false : true));
    $shippingMethod = old('shipping_method', $sessionData['shipping_method'] ?? 'delivery');
    $deliveryMode = old('delivery_mode', $sessionData['delivery_mode'] ?? 'asap');
    $paymentMethod = old('payment_method', $sessionData['payment_method'] ?? 'liqpay');
@endphp

@section('content')
    <div class="mx-auto desk:w-[1208px] px-4 py-6 md:p-6 max-w-full">
        <h1 class="text-[26px] md:text-2xl font-semibold mb-4 md:mb-6">{{ st('cart.miy-zakaz', 'Мій заказ') }}</h1>

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

        <form action="{{ route('checkout.submit') }}"
              method="POST" class="space-y-6" data-checkout-form novalidate >
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

@push('scripts')
{{-- jQuery необходим для map-cart.js --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
{{-- Загружаем Google Maps API асинхронно, чтобы не блокировать Alpine.js --}}
<script>
(function() {
    // Флаг для отслеживания загрузки Google Maps API
    window.__googleMapsLoading = true;
    window.__googleMapsLoaded = false;
    
    // Callback для инициализации после загрузки Google Maps
    window.__onGoogleMapsLoaded = function() {
        window.__googleMapsLoaded = true;
        window.__googleMapsLoading = false;
    };
    
    // Загружаем Google Maps API асинхронно
    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places,geometry&callback=__onGoogleMapsLoaded';
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
})();
</script>
{{-- Загружаем map-cart.js для доступа к deliveryAreas --}}
@vite(['resources/js/map-cart.js'])
<script>
(function() {
    // Сохранение данных формы в сессию
    const saveFormData = debounce(function() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) return;

        // Получаем значения всех полей
        const contactName = document.getElementById('contact_name')?.value || '';
        const contactPhone = document.getElementById('contact_phone')?.value || '';
        const contactEmail = document.getElementById('contact_email')?.value || '';

        // Способ получения (из Alpine.js или из hidden input)
        const shippingMethodEl = form.querySelector('[name="shipping_method"]');
        const shippingMethod = shippingMethodEl?.value || '';

        // Выбранный адрес
        const selectedAddressId = form.querySelector('[name="selected_address_id"]:checked')?.value || '';

        // Использовать новый адрес (из Alpine.js или из hidden input)
        const useNewAddressEl = form.querySelector('[name="use_new_address"]');
        const useNewAddress = useNewAddressEl?.value || '0';

        // Данные нового адреса
        const addrStreet = document.getElementById('checkout-address-street')?.value || '';
        const addrHouse = document.getElementById('checkout-address-house')?.value || '';
        const addrApartment = form.querySelector('[name="addr[apartment]"]')?.value || '';
        const addrIntercom = form.querySelector('[name="addr[intercom]"]')?.value || '';
        const addrFloor = form.querySelector('[name="addr[floor]"]')?.value || '';
        const addrPorch = form.querySelector('[name="addr[porch]"]')?.value || '';
        const addrComment = form.querySelector('[name="addr[comment]"]')?.value || '';
        const addrIsPrivateHouse = form.querySelector('[name="addr[is_private_house]"]')?.checked ? '1' : '0';
        const addrType = form.querySelector('[name="addr[type]"]')?.value || '';

        // Условия доставки
        const deliveryMode = form.querySelector('[name="delivery_mode"]')?.value || '';
        const deliveryDate = form.querySelector('[name="delivery_date"]')?.value || '';
        const deliveryTime = form.querySelector('[name="delivery_time"]')?.value || '';

        // Способ оплаты
        const paymentMethod = form.querySelector('[name="payment_method"]:checked')?.value || '';

        // Комментарии
        const commentKitchen = form.querySelector('[name="comment_kitchen"]')?.value || '';
        const commentCourier = form.querySelector('[name="comment_courier"]')?.value || '';

        const payload = {
            contact_name: contactName,
            contact_phone: contactPhone,
            contact_email: contactEmail,
            shipping_method: shippingMethod,
            selected_address_id: selectedAddressId,
            use_new_address: useNewAddress,
            delivery_mode: deliveryMode,
            delivery_date: deliveryDate,
            delivery_time: deliveryTime,
            payment_method: paymentMethod,
            comment_kitchen: commentKitchen,
            comment_courier: commentCourier,
            addr_street: addrStreet,
            addr_house: addrHouse,
            addr_apartment: addrApartment,
            addr_intercom: addrIntercom,
            addr_floor: addrFloor,
            addr_porch: addrPorch,
            addr_comment: addrComment,
            addr_is_private_house: addrIsPrivateHouse,
            addr_type: addrType,
        };

        fetch('{{ route('checkout.save-form-data') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload)
        }).catch(err => console.error('Error saving form data:', err));
    }, 500);

    // Функция debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Привязываем сохранение ко всем полям формы
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('[data-checkout-form]');
        if (!form) return;

        // События для всех полей ввода
        form.addEventListener('input', saveFormData);
        form.addEventListener('change', saveFormData);

        // События для radio и checkbox
        form.addEventListener('click', function(e) {
            if (e.target.type === 'radio' || e.target.type === 'checkbox') {
                saveFormData();
            }
        });
    });
})();

// Инициализация автозаполнения адреса для checkout с фильтрацией по зонам доставки
(function() {
    function initCheckoutAutocomplete() {
        // Проверяем, что все зависимости загружены
        if (typeof window.initAddressAutocomplete === 'undefined') {
            setTimeout(initCheckoutAutocomplete, 200);
            return;
        }
        
        // Ждем загрузки Google Maps API (если еще не загружен)
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            // Проверяем флаг загрузки
            if (window.__googleMapsLoading && !window.__googleMapsLoaded) {
                setTimeout(initCheckoutAutocomplete, 200);
                return;
            }
            // Если Google Maps не загружается, используем стандартное автозаполнение
            console.warn('Google Maps API не загружен, используем стандартное автозаполнение');
            window.initAddressAutocomplete({
                streetInputId: 'checkout-address-street',
                houseInputId: 'checkout-address-house',
                cityInputSelector: '#checkout-address-city',
                kyivOnly: true,
                filterByDeliveryZone: false,
                googleMapsKey: window.GOOGLE_MAPS_API_KEY,
            });
            return;
        }
        
        // Используем единую логику с фильтрацией по зонам доставки
        window.initAddressAutocomplete({
            streetInputId: 'checkout-address-street',
            houseInputId: 'checkout-address-house',
            cityInputSelector: '#checkout-address-city', // Передаем селектор скрытого поля города
            kyivOnly: true,
            filterByDeliveryZone: true, // Включаем фильтрацию по зонам доставки
            googleMapsKey: window.GOOGLE_MAPS_API_KEY,
        });
    }
    
    // Ждем загрузки DOM и всех зависимостей
    function waitForDependencies() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(initCheckoutAutocomplete, 500);
            });
        } else {
            setTimeout(initCheckoutAutocomplete, 500);
        }
    }
    
    // Если Google Maps загружается асинхронно, ждем его загрузки
    if (window.__googleMapsLoading) {
        const checkGoogleMaps = setInterval(function() {
            if (window.__googleMapsLoaded || (typeof google !== 'undefined' && google.maps && google.maps.places)) {
                clearInterval(checkGoogleMaps);
                waitForDependencies();
            }
        }, 200);
        
        // Таймаут на случай, если Google Maps не загрузится
        setTimeout(function() {
            clearInterval(checkGoogleMaps);
            waitForDependencies();
        }, 10000);
    } else {
        waitForDependencies();
    }
})();
</script>
<script>
    (function () {
        // Мобильный порядок (строго по твоему списку)
        const mobileOrder = [
            'blk-items',       // Мой заказ
            'blk-toggle',      // доставка/самовывоз
            'blk-contact',     // контактные
            'blk-address',     // адрес
            'blk-extras',      // комментарии
            'blk-conditions',  // условия доставки
            'blk-promocode',   // промокод
            'blk-promotions',  // акции
            'blk-bonus',       // бонусы
            'blk-totals',      // сумма
            'blk-pay',         // способ оплаты
            'blk-submit',      // согласие + кнопка
            'blk-earned',

        ];

        // Desktop-раскладка: toggle сверху, форма слева, итог справа
        const desktopLeft  = ['blk-contact','blk-address','blk-extras','blk-conditions','blk-promotions','blk-pay'];
        const desktopRight = ['blk-items','blk-promocode','blk-bonus','blk-totals','blk-submit','blk-earned'];

        function applyLayout() {
            const isMobile = window.matchMedia('(max-width: 1023px)').matches;

            const left   = document.getElementById('col-left');
            const right  = document.getElementById('col-right');
            const toggle = document.getElementById('blk-toggle');

            if (!left || !right || !toggle) return;

            if (isMobile) {
                // На мобилке: прячем правую колонку и складываем ВСЁ в левую по нужному порядку
                right.style.display = 'none';

                mobileOrder.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) left.appendChild(el);
                });

            } else {
                // На десктопе: возвращаем правую колонку и раскладываем обратно
                right.style.display = '';

                // toggle должен быть НАД колонками (как ты хочешь)
                // он уже там в DOM — просто убедимся, что он стоит перед блоком колонок
                const colsWrap = left.parentElement; // общий wrapper колонок
                if (colsWrap && colsWrap.parentElement) {
                    colsWrap.parentElement.insertBefore(toggle, colsWrap);
                }

                desktopLeft.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) left.appendChild(el);
                });

                desktopRight.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) right.appendChild(el);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', applyLayout);
        window.addEventListener('resize', applyLayout);
    })();
</script>
<script>
    (function () {
        function getFieldValue(form, name) {
            const els = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');
            if (!els.length) return '';

            const first = els[0];
            if (first.type === 'radio') {
                const checked = form.querySelector('[name="' + CSS.escape(name) + '"]:checked');
                return checked ? (checked.value || '') : '';
            }
            if (first.type === 'checkbox') {
                return first.checked ? '1' : '';
            }
            return (first.value || '').trim();
        }

        function shouldValidate(form, field) {
            const rule = field.getAttribute('data-required-if');
            if (!rule) return true;

            const parts = rule.split(';').map(s => s.trim()).filter(Boolean);
            for (const p of parts) {
                const [depName, depVal] = p.split('=').map(s => (s || '').trim());
                if (!depName) continue;
                if (String(getFieldValue(form, depName)) !== String(depVal)) return false;
            }
            return true;
        }

        function getWrap(form, name) {
            return form.querySelector('[data-field-wrap="'+CSS.escape(name)+'"] .tp-float-wrap');
        }

        function showError(form, name) {
            const err = form.querySelector('[data-error-for="'+CSS.escape(name)+'"]');
            if (err) err.classList.remove('hidden');

            // float
            const wrap = form.querySelector('[data-field-wrap="'+CSS.escape(name)+'"] .tp-float-wrap');
            if (wrap) { wrap.classList.add('is-invalid'); return; }

            // обычные inputs/select
            const el = form.querySelector('[name="'+CSS.escape(name)+'"]');
            if (el) el.classList.add('is-invalid');
        }

        function clearError(form, name) {
            const err = form.querySelector('[data-error-for="'+CSS.escape(name)+'"]');
            if (err) err.classList.add('hidden');

            const wrap = form.querySelector('[data-field-wrap="'+CSS.escape(name)+'"] .tp-float-wrap');
            if (wrap) { wrap.classList.remove('is-invalid'); return; }

            const el = form.querySelector('[name="'+CSS.escape(name)+'"]');
            if (el) el.classList.remove('is-invalid');
        }



        function focusField(form, name) {
            const el = form.querySelector('[name="'+CSS.escape(name)+'"]') || document.getElementById(name);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => el.focus(), 150);
        }

        function validateForm(form) {
            // очистка
            form.querySelectorAll('[data-error-for]').forEach(p => p.classList.add('hidden'));
            form.querySelectorAll('.tp-float-wrap.is-invalid').forEach(w => w.classList.remove('is-invalid'));

            let firstInvalidName = null;

            const requiredFields = form.querySelectorAll('[data-required]');
            requiredFields.forEach(field => {
                if (!shouldValidate(form, field)) return;

                const name = field.getAttribute('name') || field.getAttribute('id');
                if (!name) return;

                // не валидируем disabled
                if (field.disabled) return;

                const val = field.type === 'checkbox'
                    ? (field.checked ? '1' : '')
                    : (field.type === 'radio' ? getFieldValue(form, field.name) : (field.value || '').trim());

                if (!val) {
                    showError(form, name);
                    if (!firstInvalidName) firstInvalidName = name;
                }
            });

            if (firstInvalidName) {
                focusField(form, firstInvalidName);
                return false;
            }

            return true;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-checkout-form]');
            if (!form) return;

            // 1) Перехват сабмита: если ошибки — НЕ отправляем на сервер
            form.addEventListener('submit', function (e) {
                if (!validateForm(form)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }, true);

            // 2) При вводе — убираем ошибку с конкретного поля
            form.addEventListener('input', function (e) {
                const t = e.target;
                if (!t) return;
                const name = t.getAttribute('name') || t.getAttribute('id');
                if (name) clearError(form, name);
            });

            form.addEventListener('change', function (e) {
                const t = e.target;
                if (!t) return;
                const name = t.getAttribute('name') || t.getAttribute('id');
                if (name) clearError(form, name);
            });
        });
    })();
</script>
<script>
    window.resetNewAddress = function(btn){
        const form = btn.closest('form');
        if (!form) return;

        // закрыть форму: если есть Alpine useNew/isPrivate — дернем через closest root
        const root = btn.closest('[x-data]');
        try {
            // если Alpine доступен, можно дернуть напрямую:
            // Alpine.$data(root).useNew = false; Alpine.$data(root).isPrivate = false;
        } catch(e){}

        const names = ['addr[street]','addr[house]','addr[apartment]','addr[porch]','addr[intercom]','addr[floor]','addr[comment]'];
        names.forEach((name) => {
            const el = form.querySelector('[name="'+name+'"]');
            if (el) { el.value=''; el.dispatchEvent(new Event('input',{bubbles:true})); }
        });

        const priv = form.querySelector('[name="addr[is_private_house]"]');
        if (priv) priv.checked = false;

        form.querySelectorAll('.tp-error').forEach(p => p.classList.add('hidden'));
        form.querySelectorAll('.tp-float-wrap.is-invalid').forEach(w => w.classList.remove('is-invalid'));
    }
</script>




@endpush
