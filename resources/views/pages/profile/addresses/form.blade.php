@extends('layouts.app')

@section('title', $address->exists ? st('profile.addresses.edit_title', 'Редагувати адресу') : st('profile.addresses.create_title', 'Додати адресу'))

@section('content')
    <div class="mx-auto desk:w-[1200px] px-4 md:px-6 desk:px-0">
        <h1 class="sr-only md:not-sr-only md:text-[28px] md:leading-8 font-bold text-[#19191A] md:mb-4">
            {{ $address->exists ? st('profile.addresses.edit_title', 'Редагувати адресу') : st('profile.addresses.create_title', 'Додати адресу') }}
        </h1>

        <div class="xl:grid xl:grid-cols-[240px,1fr] md:gap-6">
            {{-- Левое меню (desktop) --}}
            <aside class="hidden xl:block">
                @include('pages.menu.profile-menu')
            </aside>

            {{-- Контент --}}
            <main>
                <div class="bg-white rounded-[6px] ring-1 ring-black/10 p-4 md:p-6">
                    <form action="{{ $address->exists ? route('profile.addresses.update', $address) : route('profile.addresses.store') }}"
                          method="POST">
                        @csrf
                        @if($address->exists)
                            @method('PUT')
                        @endif

                        <div class="space-y-4">
                            {{-- Город --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ st('address.form.city', 'Місто') }}
                                </label>
                                <input type="text"
                                       name="city"
                                       value="{{ old('city', $address->city) }}"
                                       class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                              text-[16px] leading-[22px]
                                              focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                              transition">
                                @error('city')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Улица --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.street', 'Вулиця') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           id="profile-address-street"
                                           name="street"
                                           required
                                           value="{{ old('street', $address->street) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('street')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Дом --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.house', 'Дім') }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           id="profile-address-house"
                                           name="house"
                                           required
                                           value="{{ old('house', $address->house) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('house')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Квартира --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.apartment', 'Квартира') }}
                                    </label>
                                    <input type="text"
                                           name="apartment"
                                           value="{{ old('apartment', $address->apartment) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('apartment')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Домофон --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.intercom', 'Домофон') }}
                                    </label>
                                    <input type="text"
                                           name="intercom"
                                           value="{{ old('intercom', $address->intercom) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('intercom')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Этаж --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.floor', 'Поверх') }}
                                    </label>
                                    <input type="number"
                                           name="floor"
                                           value="{{ old('floor', $address->floor) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('floor')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Подъезд --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        {{ st('address.form.porch', 'Під\'їзд') }}
                                    </label>
                                    <input type="text"
                                           name="entrance"
                                           value="{{ old('entrance', $address->entrance) }}"
                                           class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                                  text-[16px] leading-[22px]
                                                  focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                  transition">
                                    @error('entrance')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Тип адреса --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ st('address.form.type', 'Тип адреси') }}
                                </label>
                                <select name="type"
                                        class="w-full h-[46px] rounded-[6px] border border-[#E5E7EB] px-4
                                               text-[16px] leading-[22px]
                                               focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                               transition">
                                    <option value="">{{ st('address.form.type_select', 'Оберіть тип') }}</option>
                                    <option value="home" {{ old('type', $address->type) === 'home' ? 'selected' : '' }}>
                                        {{ st('address.type.home', 'Дім') }}
                                    </option>
                                    <option value="work" {{ old('type', $address->type) === 'work' ? 'selected' : '' }}>
                                        {{ st('address.type.work', 'Робота') }}
                                    </option>
                                    <option value="friends" {{ old('type', $address->type) === 'friends' ? 'selected' : '' }}>
                                        {{ st('address.type.friends', 'Друзі') }}
                                    </option>
                                </select>
                                @error('type')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Примечание --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ st('address.form.note', 'Примітка') }}
                                </label>
                                <textarea name="note"
                                          rows="3"
                                          class="w-full rounded-[6px] border border-[#E5E7EB] px-4 py-2
                                                 text-[16px] leading-[22px]
                                                 focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                                                 transition">{{ old('note', $address->note) }}</textarea>
                                @error('note')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Частный дом --}}
                            <div>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox"
                                           name="is_private_house"
                                           value="1"
                                           {{ old('is_private_house', $address->is_private_house) ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#FF7500] border-gray-300 rounded
                                                  focus:ring-[#FF7500] focus:ring-2">
                                    <span class="text-sm text-gray-700">
                                        {{ st('address.form.private_house', 'Це приватний будинок') }}
                                    </span>
                                </label>
                            </div>

                            {{-- Кнопки --}}
                            <div class="flex items-center gap-4 pt-4 border-t">
                                <button type="submit"
                                        class="px-6 py-2 bg-[#FF7500] text-white rounded-lg hover:bg-orange-600 transition">
                                    {{ $address->exists ? st('profile.addresses.update', 'Оновити') : st('profile.addresses.save', 'Зберегти') }}
                                </button>
                                <a href="{{ route('profile.addresses.index') }}"
                                   class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                                    {{ st('profile.addresses.cancel', 'Скасувати') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        let autocompleteInitialized = false;

        function initProfileAddressAutocomplete() {
            if (autocompleteInitialized) return;

            const streetInput = document.getElementById('profile-address-street');
            const houseInput = document.getElementById('profile-address-house');
            
            if (!streetInput) {
                return;
            }

            if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
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
                        // Также извлекаем город
                        if (c.types.includes('locality') || c.types.includes('administrative_area_level_1')) {
                            const cityInput = document.querySelector('input[name="city"]');
                            if (cityInput && !cityInput.value) {
                                cityInput.value = c.long_name;
                            }
                        }
                    }

                    // Закрываем dropdown ПЕРЕД изменением значения
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
                        houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    // Триггерим событие для Alpine.js
                    streetInput.dispatchEvent(new Event('input', { bubbles: true }));

                    // Убираем фокус с поля и перемещаем на другое поле
                    setTimeout(function() {
                        streetInput.blur();
                        if (houseInput && streetNumber) {
                            houseInput.focus();
                        } else if (houseInput) {
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
                initProfileAddressAutocomplete();
                return;
            }

            // Проверяем, не загружается ли уже скрипт
            if (document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]')) {
                // Скрипт уже есть, просто ждем его загрузки
                setTimeout(initProfileAddressAutocomplete, 1000);
                return;
            }

            // Создаем callback функцию глобально
            window.initProfileAddressAutocompleteCallback = function() {
                initProfileAddressAutocomplete();
            };

            const script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config("services.google_maps.key") }}&libraries=places&callback=initProfileAddressAutocompleteCallback';
            script.defer = true;
            script.async = true;
            document.head.appendChild(script);
        }

        // Инициализируем после загрузки DOM
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(loadGoogleMapsAPI, 500);
            });
        } else {
            setTimeout(loadGoogleMapsAPI, 500);
        }
    })();
    </script>
    @endpush
@endsection

