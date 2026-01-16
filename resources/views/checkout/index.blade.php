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

        <form action="{{ route('checkout.submit') }}" method="POST" class="space-y-6" data-checkout-form >
            @csrf

            <div
                x-data="{
            method: '{{ $shippingMethod }}',
            useNew: {{ $useNewInitial ? 'true' : 'false' }},
            deliveryMode: '{{ $deliveryMode }}',
            paymentMethod: '{{ $paymentMethod }}',
            init() {
                // Загружаем данные из сессии при инициализации после небольшой задержки
                this.$nextTick(() => {
                    @if(!empty($sessionData))
                        const formData = @js($sessionData);
                        // Обновляем способ получения
                        if (formData.shipping_method) {
                            this.method = formData.shipping_method;
                        }
                        // Обновляем режим доставки
                        if (formData.delivery_mode) {
                            this.deliveryMode = formData.delivery_mode;
                        }
                        // Обновляем способ оплаты
                        if (formData.payment_method) {
                            this.paymentMethod = formData.payment_method;
                            const radio = document.querySelector(`[name=\"payment_method\"][value=\"${formData.payment_method}\"]`);
                            if (radio) radio.checked = true;
                        }
                    @endif
                });
            }
                    }"
                class="mb-6"
            >
                {{-- Переключатель способа получения + hidden --}}
                @include('checkout.partials._shipping-toggle')

                {{-- Весь блок внутри страницы "Мой заказ" --}}
                <div class="flex flex-col-reverse lg:flex-row justify-center gap-4 md:gap-6 lg:gap-[32px] mt-4 md:mt-6">
                    {{-- Левая колонка (форма) - на десктопе слева --}}
                    <div class="w-full lg:w-[580px] space-y-4 md:space-y-6">
                        @include('checkout.partials._contact', ['sessionData' => $sessionData])
                        @include('checkout.partials._delivery-address', ['sessionData' => $sessionData, 'useNewInitial' => $useNewInitial, 'selectedId' => $selectedId])
                        @include('checkout.partials._pickup-locations')
                        @include('checkout.partials._delivery-conditions', ['sessionData' => $sessionData, 'deliveryMode' => $deliveryMode])
                        @include('checkout.partials._promotions')
                        @include('checkout.partials._payment-methods', ['sessionData' => $sessionData, 'paymentMethod' => $paymentMethod])
                        @include('checkout.partials._extras', ['sessionData' => $sessionData])
                    </div>

                    {{-- Правая колонка (корзина, промокод, итоги) - на десктопе справа --}}
                    <div class="w-full lg:w-[585px] space-y-4 md:space-y-6">
                        @include('checkout.partials._order-items')
                        @include('checkout.partials._summary')
                        @include('checkout.partials._bonus-earned')
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

(function() {
    let autocompleteInitialized = false;
    let initAttempts = 0;
    const maxAttempts = 10;

    function initCheckoutAddressAutocomplete() {
        // Проверяем, не инициализирован ли уже autocomplete
        if (autocompleteInitialized) return;

        const streetInput = document.getElementById('checkout-address-street');
        const houseInput = document.getElementById('checkout-address-house');
        
        if (!streetInput) {
            // Поле еще не доступно, попробуем позже (но не более maxAttempts раз)
            initAttempts++;
            if (initAttempts < maxAttempts) {
                setTimeout(initCheckoutAddressAutocomplete, 500);
            }
            return;
        }

        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            // Google Maps API еще не загружен, попробуем позже
            initAttempts++;
            if (initAttempts < maxAttempts) {
                setTimeout(initCheckoutAddressAutocomplete, 500);
            }
            return;
        }

        var options = {
            componentRestrictions: { country: 'ua' },
            types: ['address'],
        };

        try {
            var autocomplete = new google.maps.places.Autocomplete(streetInput, options);

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) return;

                // Извлекаем части адреса
                const comps = place.address_components || [];
                let street = '';
                let streetNumber = '';

                for (const c of comps) {
                    if (c.types.includes('route')) {
                        street = c.long_name;
                    }
                    if (c.types.includes('street_number')) {
                        streetNumber = c.long_name;
                    }
                }

                // Закрываем dropdown ПЕРЕД изменением значения
                // Находим и скрываем элементы Google Places dropdown
                const pacContainer = document.querySelector('.pac-container');
                if (pacContainer) {
                    pacContainer.style.display = 'none';
                }

                // Заполняем поле улицы (только название улицы без номера)
                if (street) {
                    streetInput.value = street;
                }

                // Заполняем поле дома, если номер дома есть
                if (streetNumber && houseInput) {
                    houseInput.value = streetNumber;
                    // Триггерим событие для Alpine.js
                    houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Триггерим событие для Alpine.js
                streetInput.dispatchEvent(new Event('input', { bubbles: true }));

                // Убираем фокус с поля и перемещаем на другое поле
                setTimeout(function() {
                    streetInput.blur();
                    // Если есть поле дома, перемещаем фокус на него
                    if (houseInput && streetNumber) {
                        houseInput.focus();
                    } else if (houseInput) {
                        // Если номера дома нет, все равно перемещаем фокус для закрытия dropdown
                        houseInput.focus();
                        setTimeout(() => houseInput.blur(), 50);
                    }
                    
                    // Дополнительно скрываем dropdown на случай, если он все еще виден
                    const pacContainer = document.querySelector('.pac-container');
                    if (pacContainer) {
                        pacContainer.style.display = 'none';
                    }
                }, 50);
            });

            // Закрываем dropdown при потере фокуса
            streetInput.addEventListener('blur', function() {
                setTimeout(function() {
                    const pacContainer = document.querySelector('.pac-container');
                    if (pacContainer) {
                        pacContainer.style.display = 'none';
                    }
                }, 200);
            });

            // Дополнительно: закрываем dropdown при клике вне его
            document.addEventListener('click', function(e) {
                const pacContainer = document.querySelector('.pac-container');
                if (pacContainer && !pacContainer.contains(e.target) && e.target !== streetInput) {
                    pacContainer.style.display = 'none';
                }
            });

            autocompleteInitialized = true;
        } catch (e) {
            console.error('Error initializing Google Places Autocomplete:', e);
        }
    }

    // Функция для загрузки Google Maps API
    function loadGoogleMapsAPI() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initCheckoutAddressAutocomplete();
            return;
        }

        // Проверяем, не загружается ли уже скрипт
        if (document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]')) {
            // Скрипт уже есть, просто ждем его загрузки
            setTimeout(initCheckoutAddressAutocomplete, 1000);
            return;
        }

        // Создаем callback функцию глобально
        window.initCheckoutAddressAutocompleteCallback = function() {
            initCheckoutAddressAutocomplete();
        };

        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config("services.google_maps.key") }}&libraries=places&callback=initCheckoutAddressAutocompleteCallback';
        script.defer = true;
        script.async = true;
        document.head.appendChild(script);
    }

    // Инициализируем после загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Небольшая задержка, чтобы убедиться, что Alpine.js инициализировал поля
            setTimeout(loadGoogleMapsAPI, 500);
        });
    } else {
        setTimeout(loadGoogleMapsAPI, 500);
    }
})();
</script>
@endpush
