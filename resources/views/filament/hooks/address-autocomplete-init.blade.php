{{-- Скрипт для инициализации автокомплита адресов в Filament админке --}}
@php
    // Ограничиваем запуск автокомплита только страницами заказов,
    // чтобы не дергать логику на логине и прочих экранах Filament.
    $isOrderPage = request()->routeIs('filament.admin.resources.shop.orders.*');
@endphp

@if(config('services.google_maps.key') && $isOrderPage)
{{-- Загружаем библиотеку address-autocomplete.js через app.js --}}
@php
    // Пытаемся найти скомпилированный app.js в build/assets
    $manifestPath = public_path('build/.vite/manifest.json');
    $appJsUrl = null;
    
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
        // Ищем app.js, который включает address-autocomplete
        foreach ($manifest as $key => $value) {
            if ($key === 'resources/js/app.js' || (isset($value['isEntry']) && str_contains($key, 'app.js'))) {
                $appJsUrl = asset('build/' . ($value['file'] ?? $key));
                break;
            }
        }
    }
@endphp
@if($appJsUrl)
<script src="{{ $appJsUrl }}" defer></script>
@endif
<script>
    // Устанавливаем глобальную переменную для API ключа
    if (typeof window.GOOGLE_MAPS_API_KEY === 'undefined') {
        window.GOOGLE_MAPS_API_KEY = '{{ config("services.google_maps.key") }}';
    }
    
    // Инициализируем флаги загрузки Google Maps API, если они еще не установлены
    if (typeof window.__googleMapsLoading === 'undefined') {
        window.__googleMapsLoading = false;
    }
    if (typeof window.__googleMapsLoaded === 'undefined') {
        window.__googleMapsLoaded = (typeof google !== 'undefined' && google.maps);
    }
    
    // НЕ загружаем Google Maps API здесь - он может быть уже загружен Filament или другими скриптами
    // Просто ждем, пока он загрузится, если еще не загружен
    if (typeof google === 'undefined' || !google.maps) {
        // Проверяем, не загружается ли уже API через другой скрипт
        const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
        if (existingScript) {
            console.log('Filament Autocomplete: Google Maps API script already exists, waiting for it to load...');
            // Ждем загрузки существующего скрипта
            const checkInterval = setInterval(function() {
                if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                    window.__googleMapsLoaded = true;
                    window.__googleMapsLoading = false;
                    clearInterval(checkInterval);
                    console.log('Filament Autocomplete: Google Maps API loaded from existing script');
                }
            }, 100);
            // Останавливаем проверку через 10 секунд
            setTimeout(function() {
                clearInterval(checkInterval);
                if (!window.__googleMapsLoaded) {
                    console.warn('Filament Autocomplete: Google Maps API did not load within 10 seconds');
                }
            }, 10000);
        } else {
            // Если скрипта нет, но API не загружен, возможно он загружается асинхронно
            // Просто ждем и проверяем периодически
            const checkInterval = setInterval(function() {
                if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                    window.__googleMapsLoaded = true;
                    window.__googleMapsLoading = false;
                    clearInterval(checkInterval);
                    console.log('Filament Autocomplete: Google Maps API loaded');
                }
            }, 200);
            // Останавливаем проверку через 5 секунд
            setTimeout(function() {
                clearInterval(checkInterval);
            }, 5000);
        }
    } else {
        // API уже загружен
        window.__googleMapsLoaded = true;
        window.__googleMapsLoading = false;
    }
    
    // Функция для получения координат адреса через Google Geocoding API
    window.getCoordinatesForAddressString = function(addressString, callback) {
        if (typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
            console.warn('Filament Autocomplete: Google Maps API not loaded, waiting...');
            // Ждем загрузки API
            if (window.__googleMapsLoading) {
                setTimeout(function() {
                    window.getCoordinatesForAddressString(addressString, callback);
                }, 500);
            } else {
                callback(null);
            }
            return;
        }
        
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ address: addressString, componentRestrictions: { country: 'ua' } }, function(results, status) {
            if (status === 'OK' && results && results[0]) {
                const location = results[0].geometry.location;
                callback({
                    latitude: location.lat(),
                    longitude: location.lng(),
                    formatted_address: results[0].formatted_address
                });
            } else {
                console.warn('Filament Autocomplete: Geocoding failed', status);
                callback(null);
            }
        });
    };
    
    // Функция для обработки выбора адреса из списка
    window.handleSelectedAddressChange = function() {
        // Ждем загрузки Google Maps API
        if (typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
            if (!window.__googleMapsLoaded) {
                setTimeout(window.handleSelectedAddressChange, 200);
                return;
            }
        }
        
        // Ищем поле выбора адреса
        const form = document.querySelector('form#form');
        if (!form) {
            console.warn('Filament Autocomplete: Form not found for address selection');
            return;
        }
        
        // Ищем Select поле selected_address_id
        const addressSelect = form.querySelector('select[name*="selected_address_id"], select[wire\\:model*="selected_address_id"]');
        if (!addressSelect) {
            console.warn('Filament Autocomplete: selected_address_id field not found');
            return;
        }
        
        // Обработчик изменения выбранного адреса
        addressSelect.addEventListener('change', function() {
            const selectedAddressId = this.value;
            if (!selectedAddressId || selectedAddressId === '-1') {
                return;
            }
            
            console.log('Filament Autocomplete: Address selected from list', selectedAddressId);
            
            // Получаем данные адреса из формы
            const addressData = {
                street: null,
                house: null,
                city: 'Київ'
            };
            
            // Ищем поля адреса в форме
            const streetInput = form.querySelector('input[name*="[street]"], input[wire\\:model*="street"]');
            const houseInput = form.querySelector('input[name*="[house]"], input[wire\\:model*="house"]');
            const cityInput = form.querySelector('input[name*="[city]"], input[wire\\:model*="city"]');
            
            if (streetInput) addressData.street = streetInput.value;
            if (houseInput) addressData.house = houseInput.value;
            if (cityInput) addressData.city = cityInput.value || 'Київ';
            
            // Проверяем, есть ли уже координаты
            const latitudeInput = form.querySelector('input[name*="[latitude]"], input[wire\\:model*="latitude"]');
            const longitudeInput = form.querySelector('input[name*="[longitude]"], input[wire\\:model*="longitude"]');
            
            if (latitudeInput && longitudeInput && latitudeInput.value && longitudeInput.value) {
                console.log('Filament Autocomplete: Coordinates already exist', {
                    lat: latitudeInput.value,
                    lng: longitudeInput.value
                });
                // Координаты уже есть, триггерим расчет доставки
                const updateKey = 'coords_' + latitudeInput.value + '_' + longitudeInput.value + '_' + Date.now();
                const deliveryTrigger = form.querySelector('input[name*="delivery_coords_trigger"]');
                if (deliveryTrigger) {
                    deliveryTrigger.value = updateKey;
                    deliveryTrigger.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    deliveryTrigger.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                }
                return;
            }
            
            // Формируем строку адреса для геокодинга
            const addressParts = [];
            if (addressData.street) addressParts.push(addressData.street);
            if (addressData.house) addressParts.push(addressData.house);
            if (addressData.city) addressParts.push(addressData.city);
            const addressString = addressParts.join(', ') + ', Украина';
            
            if (!addressData.street) {
                console.warn('Filament Autocomplete: Street not found in address data');
                return;
            }
            
            console.log('Filament Autocomplete: Getting coordinates for address', addressString);
            
            // Получаем координаты через Geocoding API
            window.getCoordinatesForAddressString(addressString, function(coords) {
                if (!coords) {
                    console.warn('Filament Autocomplete: Failed to get coordinates for selected address');
                    return;
                }
                
                console.log('Filament Autocomplete: Got coordinates for selected address', coords);
                
                // Обновляем скрытые поля координат
                if (latitudeInput && longitudeInput) {
                    latitudeInput.value = coords.latitude;
                    longitudeInput.value = coords.longitude;
                    
                    // Триггерим события
                    latitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    longitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    latitudeInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    longitudeInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    
                    // Триггерим расчет доставки
                    const updateKey = 'coords_' + coords.latitude + '_' + coords.longitude + '_' + Date.now();
                    const deliveryTrigger = form.querySelector('input[name*="delivery_coords_trigger"]');
                    if (deliveryTrigger) {
                        deliveryTrigger.value = updateKey;
                        deliveryTrigger.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        deliveryTrigger.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                    
                    // Также обновляем delivery_price_auto
                    const deliveryPriceAuto = form.querySelector('input[name*="delivery_price_auto"]');
                    if (deliveryPriceAuto) {
                        const autoUpdateKey = 'auto_' + coords.latitude + '_' + coords.longitude + '_' + Date.now();
                        deliveryPriceAuto.value = autoUpdateKey;
                        deliveryPriceAuto.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        deliveryPriceAuto.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                    
                    // Обновляем formatted_address, если есть поле
                    const formattedAddressInput = form.querySelector('input[name*="[formatted_address]"], input[wire\\:model*="formatted_address"]');
                    if (formattedAddressInput && coords.formatted_address) {
                        formattedAddressInput.value = coords.formatted_address;
                        formattedAddressInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    }
                }
            });
        });
    };
    
    // Инициализируем обработчик выбора адреса после загрузки страницы
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.handleSelectedAddressChange);
    } else {
        window.handleSelectedAddressChange();
    }
    
    // Также инициализируем при обновлении Livewire (Filament использует Livewire)
    if (typeof Livewire !== 'undefined') {
        Livewire.hook('morph.updated', function() {
            setTimeout(window.handleSelectedAddressChange, 100);
        });
    }
    
    // Определяем функцию глобально, чтобы она была доступна для колбэка Google Maps
    window.initFilamentAddressAutocomplete = function() {
        // Ждем загрузки Google Maps API
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            if (!window.__googleMapsLoaded) {
                setTimeout(window.initFilamentAddressAutocomplete, 200);
                return;
            }
        }
        
        // Ищем поле "Вулиця (Київ)" в форме адреса
        // В Filament поле может быть обернуто в дополнительные div'ы, поэтому ищем input внутри
        let streetInput = null;
        
        // Сначала пробуем найти по ID (может быть контейнер или сам input)
        const elementById = document.getElementById('filament-address-street-input');
        if (elementById) {
            // Если это input, используем его
            if (elementById instanceof HTMLInputElement && elementById.type === 'text') {
                streetInput = elementById;
            } else {
                // Если это контейнер, ищем input внутри
                const inputInside = elementById.querySelector('input[type="text"]');
                if (inputInside instanceof HTMLInputElement) {
                    streetInput = inputInside;
                }
            }
        }
        
        // Если не нашли по ID, ищем по name атрибуту или data-атрибуту
        if (!streetInput) {
            const form = document.querySelector('form[wire\\:id]') || document.querySelector('form');
            if (form) {
                // Ищем input с name, содержащим street_place_id
                const inputs = form.querySelectorAll('input[name*="street_place_id"]');
                for (let input of inputs) {
                    if (input instanceof HTMLInputElement && (input.type === 'text' || !input.type)) {
                        streetInput = input;
                        break;
                    }
                }
                
                // Если не нашли, ищем по data-атрибуту
                if (!streetInput) {
                    const dataInputs = form.querySelectorAll('input[data-address-autocomplete="true"]');
                    for (let input of dataInputs) {
                        if (input instanceof HTMLInputElement && (input.type === 'text' || !input.type)) {
                            streetInput = input;
                            break;
                        }
                    }
                }
                
                // Если все еще не нашли, ищем любой input внутри элемента с ID
                if (!streetInput && elementById) {
                    const allInputs = elementById.querySelectorAll('input');
                    for (let input of allInputs) {
                        if (input instanceof HTMLInputElement && (input.type === 'text' || !input.type)) {
                            streetInput = input;
                            break;
                        }
                    }
                }
            }
        }
        
        // Проверяем, что это действительно input элемент.
        // Если поле ещё не загрузилось, просто тихо ждём и пробуем снова,
        // без спама в консоль.
        if (!streetInput || !(streetInput instanceof HTMLInputElement)) {
            setTimeout(window.initFilamentAddressAutocomplete, 500);
            return;
        }
        
        console.log('Filament Autocomplete: Street input found', {
            id: streetInput.id,
            name: streetInput.name,
            value: streetInput.value
        });
        
        // Проверяем, не инициализирован ли уже автокомплит
        // Если уже инициализирован, удаляем старый автокомплит и создаем новый
        if (streetInput.dataset.autocompleteInitialized === 'true') {
            console.log('Filament Autocomplete: Already initialized, reinitializing...');
            // Удаляем старый автокомплит, если он есть
            if (streetInput._autocomplete) {
                google.maps.event.clearInstanceListeners(streetInput._autocomplete);
                delete streetInput._autocomplete;
            }
            // Очищаем флаг инициализации
            delete streetInput.dataset.autocompleteInitialized;
        }
        
        // Ищем поля формы
        const form = streetInput.closest('form');
        console.log('Filament Autocomplete: Searching for form fields in form', form);
        
        // В Filament поля могут иметь разные форматы имен
        // Пробуем разные варианты селекторов
        const houseInput = form?.querySelector('input[name*="[house]"]') || form?.querySelector('input[name*="house"]');
        const cityInput = form?.querySelector('input[name*="[city]"]') || form?.querySelector('input[name*="city"]');
        
        // Для latitude и longitude пробуем разные варианты
        let latitudeInput = form?.querySelector('input[name*="[latitude]"]') || 
                           form?.querySelector('input[name*="latitude"]') ||
                           form?.querySelector('input[name*="address[latitude]"]') ||
                           form?.querySelector('input[id*="latitude"]');
        
        let longitudeInput = form?.querySelector('input[name*="[longitude]"]') || 
                            form?.querySelector('input[name*="longitude"]') ||
                            form?.querySelector('input[name*="address[longitude]"]') ||
                            form?.querySelector('input[id*="longitude"]');
        
        const formattedAddressInput = form?.querySelector('input[name*="[formatted_address]"]') || 
                                     form?.querySelector('input[name*="formatted_address"]');
        // Ищем поле street (не street_place_id!)
        // В Filament поля могут иметь формат: data.address.street или address[street]
        let streetNameInput = form?.querySelector('input[name*="[street]"]') || 
                             form?.querySelector('input[name*="street"]');
        
        // Исключаем street_place_id из результатов
        if (streetNameInput && (streetNameInput.name.includes('street_place_id') || streetNameInput.id.includes('street_place_id'))) {
            streetNameInput = null;
        }
        
        // Пробуем найти поле street напрямую по более точным селекторам
        if (!streetNameInput) {
            const allInputs = form?.querySelectorAll('input[type="text"], input:not([type])');
            if (allInputs) {
                for (let input of allInputs) {
                    const name = input.name || '';
                    const id = input.id || '';
                    // Ищем поле street, но не street_place_id
                    if ((name.includes('[street]') || name.includes('.street') || name === 'street') && 
                        !name.includes('street_place_id') && !id.includes('street_place_id')) {
                        streetNameInput = input;
                        console.log('Filament Autocomplete: Found street field by scanning', {
                            name: name,
                            id: id
                        });
                        break;
                    }
                }
            }
        }
        
        // Для delivery_coords_trigger пробуем разные варианты
        let deliveryTrigger = form?.querySelector('input[name*="delivery_coords_trigger"]') ||
                             form?.querySelector('input[name="delivery_coords_trigger"]') ||
                             form?.querySelector('input[id*="delivery_coords_trigger"]');
        
        // Для delivery_price_auto пробуем разные варианты
        let deliveryPriceAuto = form?.querySelector('input[name*="delivery_price_auto"]') ||
                               form?.querySelector('input[name="delivery_price_auto"]') ||
                               form?.querySelector('input[id*="delivery_price_auto"]');
        
        console.log('Filament Autocomplete: Found form fields', {
            houseInput: !!houseInput,
            cityInput: !!cityInput,
            latitudeInput: !!latitudeInput,
            longitudeInput: !!longitudeInput,
            formattedAddressInput: !!formattedAddressInput,
            streetNameInput: !!streetNameInput,
            deliveryTrigger: !!deliveryTrigger,
            deliveryPriceAuto: !!deliveryPriceAuto
        });
        
        // Если не нашли через селекторы, пробуем найти все скрытые поля и вывести их имена для отладки
        if (!latitudeInput || !longitudeInput) {
            const allHiddenInputs = form?.querySelectorAll('input[type="hidden"]');
            console.log('Filament Autocomplete: All hidden inputs in form', Array.from(allHiddenInputs || []).map(input => ({
                name: input.name,
                id: input.id,
                value: input.value
            })));
        }
        
        // Настройки для автозаполнения (только Киев)
        const kyivBounds = new google.maps.LatLngBounds(
            new google.maps.LatLng(50.213273, 30.239440), // SW
            new google.maps.LatLng(50.590798, 30.825941)  // NE
        );
        
        const autocompleteOptions = {
            componentRestrictions: { country: 'ua' },
            types: ['address'],
            bounds: kyivBounds,
            strictBounds: true,
        };
        
        try {
            const autocomplete = new google.maps.places.Autocomplete(streetInput, autocompleteOptions);
            
            // Сохраняем ссылку на автокомплит для возможного удаления в будущем
            streetInput._autocomplete = autocomplete;
            
            // Помечаем поле как инициализированное
            streetInput.dataset.autocompleteInitialized = 'true';
            
            let isPlaceSelected = false;
            let selectedStreetValue = '';
            
            // Обработчик для предотвращения перезаписи значения после выбора места
            // Google Places может перезаписать значение введенным текстом
            const preventOverwrite = function() {
                if (streetInput.dataset.placeSelected === 'true' && 
                    streetInput.dataset.selectedValue &&
                    streetInput.value !== streetInput.dataset.selectedValue) {
                    // Проверяем, не начал ли пользователь вводить новый текст
                    const currentValue = streetInput.value;
                    const selectedValue = streetInput.dataset.selectedValue;
                    
                    // Если значение стало короче выбранного, это перезапись, а не новый ввод
                    if (currentValue.length < selectedValue.length && 
                        !selectedValue.startsWith(currentValue)) {
                        console.warn('Filament Autocomplete: Preventing value overwrite', {
                            expected: selectedValue,
                            actual: currentValue
                        });
                        streetInput.value = selectedValue;
                        streetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        streetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                }
            };
            
            // Следим за изменениями значения
            streetInput.addEventListener('input', preventOverwrite);
            streetInput.addEventListener('change', preventOverwrite);
            
            autocomplete.addListener('place_changed', function () {
                console.log('Filament Autocomplete: place_changed event fired');
                
                // Сразу закрываем панель автокомплита при выборе
                const pacContainer = document.querySelector('.pac-container');
                if (pacContainer) {
                    console.log('Filament Autocomplete: Closing pac-container immediately');
                    pacContainer.style.display = 'none';
                    pacContainer.style.visibility = 'hidden';
                    pacContainer.style.opacity = '0';
                }
                
                const place = autocomplete.getPlace();
                console.log('Filament Autocomplete: place object', place);
                
                if (!place || !place.geometry || !place.geometry.location) {
                    console.warn('Filament Autocomplete: Invalid place object', {
                        hasPlace: !!place,
                        hasGeometry: !!(place && place.geometry),
                        hasLocation: !!(place && place.geometry && place.geometry.location)
                    });
                    return;
                }
                
                console.log('Filament Autocomplete: Place is valid, processing...');
                isPlaceSelected = true;
                const comps = place.address_components || [];
                let street = '';
                let streetNumber = '';
                let city = 'Київ';
                
                // Извлекаем компоненты адреса
                comps.forEach(function(comp) {
                    const types = comp.types || [];
                    if (types.includes('route')) {
                        street = comp.long_name || comp.short_name || '';
                    }
                    if (types.includes('street_number')) {
                        streetNumber = comp.long_name || comp.short_name || '';
                    }
                    if (types.includes('locality')) {
                        city = comp.long_name || 'Київ';
                    }
                });
                
                // Формируем полное название улицы
                // Используем formatted_address из place, если доступен, иначе формируем вручную
                const fullStreet = place.formatted_address || (street + (streetNumber ? ', ' + streetNumber : ''));
                selectedStreetValue = fullStreet;
                
                // Обновляем само поле автокомплита (street_place_id) с полным адресом
                // Это важно, чтобы значение не исчезало
                if (streetInput) {
                    console.log('Filament Autocomplete: Updating street_place_id field', fullStreet);
                    
                    // ВАЖНО: Сохраняем значение в поле и предотвращаем перезапись
                    // Сначала сохраняем выбранное значение в data-атрибуте
                    streetInput.dataset.selectedValue = fullStreet;
                    streetInput.dataset.placeSelected = 'true';
                    
                    // Устанавливаем значение напрямую
                    streetInput.value = fullStreet;
                    
                    // Триггерим события для Filament/Livewire
                    streetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    streetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    // Также пробуем событие для Livewire
                    streetInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                    
                    // Проверяем и восстанавливаем значение несколько раз с разными интервалами
                    const restoreValue = function() {
                        if (streetInput.value !== fullStreet && 
                            streetInput.dataset.placeSelected === 'true' &&
                            streetInput.value.length < fullStreet.length) {
                            console.warn('Filament Autocomplete: Value was lost, restoring...', {
                                expected: fullStreet,
                                actual: streetInput.value
                            });
                            streetInput.value = fullStreet;
                            streetInput.dataset.selectedValue = fullStreet;
                            streetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                            streetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                            streetInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                            return true; // Значение было восстановлено
                        } else if (streetInput.value === fullStreet) {
                            console.log('Filament Autocomplete: Value confirmed in street_place_id field', streetInput.value);
                            return false; // Значение корректно
                        }
                        return false;
                    };
                    
                    // Проверяем через 50ms, 100ms, 200ms, 500ms, 1000ms, 2000ms
                    [50, 100, 200, 500, 1000, 2000].forEach(function(delay) {
                        setTimeout(function() {
                            restoreValue();
                        }, delay);
                    });
                    
                    // Также устанавливаем интервал для постоянной проверки в течение 3 секунд
                    let protectionInterval = setInterval(function() {
                        if (streetInput.dataset.placeSelected === 'true') {
                            restoreValue();
                        } else {
                            clearInterval(protectionInterval);
                        }
                    }, 100);
                    
                    // Снимаем защиту через 3 секунды
                    setTimeout(function() {
                        clearInterval(protectionInterval);
                        if (streetInput.value === fullStreet) {
                            streetInput.dataset.placeSelected = 'false';
                        }
                    }, 3000);
                    
                    
                    // Закрываем панель автокомплита после выбора
                    // Скрываем панель результатов сразу
                    const pacContainer = document.querySelector('.pac-container');
                    if (pacContainer) {
                        console.log('Filament Autocomplete: Hiding pac-container');
                        pacContainer.style.display = 'none';
                        pacContainer.style.visibility = 'hidden';
                        pacContainer.style.opacity = '0';
                    }
                    
                    // Также убираем фокус с поля, чтобы закрыть выпадающий список
                    // Делаем это с небольшой задержкой, чтобы дать время на обработку выбора
                    setTimeout(function() {
                        if (document.activeElement === streetInput) {
                            streetInput.blur();
                        }
                        
                        // Дополнительно проверяем и скрываем панель, если она все еще видна
                        const pacContainerCheck = document.querySelector('.pac-container');
                        if (pacContainerCheck && pacContainerCheck.style.display !== 'none') {
                            pacContainerCheck.style.display = 'none';
                            pacContainerCheck.style.visibility = 'hidden';
                            pacContainerCheck.style.opacity = '0';
                        }
                    }, 200);
                    
                    // НЕ добавляем обработчик для очистки поля - это мешает сохранению значения
                    // Пользователь может просто удалить значение вручную, если нужно искать новую улицу
                }
                
                // Обновляем поля формы
                // Сначала пробуем найти поле street по name атрибуту (наиболее надежный способ)
                // Примечание: streetInput уже объявлен в начале функции
                streetInput = null;
                if (form) {
                    // Ищем по name атрибуту - Filament может использовать разные форматы:
                    // - data.address.street
                    // - address[street]
                    // - address.street
                    // - street (если statePath не используется)
                    const streetInputs = form.querySelectorAll('input[type="text"], input:not([type])');
                    console.log('Filament Autocomplete: Searching for street field among', streetInputs.length, 'inputs');
                    
                    for (let input of streetInputs) {
                        const name = input.name || '';
                        const id = input.id || '';
                        const wireModel = input.getAttribute('wire:model') || '';
                        const xModel = input.getAttribute('x-model') || '';
                        
                        // Логируем все поля для отладки (только первые несколько)
                        if (streetInputs.length <= 15) {
                            console.log('Filament Autocomplete: Checking input', {
                                name: name,
                                id: id,
                                wireModel: wireModel,
                                xModel: xModel,
                                value: input.value
                            });
                        }
                        
                        // Ищем поле street по разным критериям, но не street_place_id
                        const isStreetField = (
                            // По name атрибуту
                            (name.includes('[street]') || 
                             name.includes('.street') || 
                             name === 'street' ||
                             name.endsWith('street') ||
                             // По wire:model
                             wireModel.includes('street') ||
                             wireModel.endsWith('.street') ||
                             wireModel.endsWith('[street]') ||
                             // По x-model (Alpine.js)
                             xModel.includes('street') ||
                             xModel.endsWith('.street') ||
                             xModel.endsWith('[street]')
                            ) &&
                            !name.includes('street_place_id') && 
                            !id.includes('street_place_id') &&
                            !id.includes('filament-address-street-input') &&
                            !wireModel.includes('street_place_id') &&
                            !xModel.includes('street_place_id')
                        );
                        
                        if (isStreetField) {
                            streetInput = input;
                            console.log('Filament Autocomplete: Found street field by name/wire:model/x-model', {
                                name: name,
                                id: id,
                                wireModel: wireModel,
                                xModel: xModel,
                                value: input.value
                            });
                            break;
                        }
                    }
                }
                
                // Если не нашли по name, используем старый способ через streetNameInput
                if (!streetInput && streetNameInput) {
                    streetInput = streetNameInput;
                    console.log('Filament Autocomplete: Using streetNameInput as fallback');
                }
                
                if (streetInput) {
                    console.log('Filament Autocomplete: Updating street field', street);
                    console.log('Filament Autocomplete: streetInput details', {
                        name: streetInput.name,
                        id: streetInput.id,
                        currentValue: streetInput.value
                    });
                    
                    // Обновляем значение
                    streetInput.value = street;
                    
                    // Триггерим события для Filament/Livewire
                    streetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    streetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    streetInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                    
                    // Также пробуем обновить через Alpine.js, если доступен
                    if (typeof Alpine !== 'undefined' && streetInput.hasAttribute('x-model')) {
                        const modelName = streetInput.getAttribute('x-model');
                        console.log('Filament Autocomplete: Found x-model on street field', modelName);
                        // Alpine.js должен автоматически обновить значение через x-model
                    }
                    
                    // Проверяем, что значение обновилось
                    setTimeout(function() {
                        console.log('Filament Autocomplete: street field value after update', streetInput.value);
                        if (streetInput.value !== street) {
                            console.warn('Filament Autocomplete: street field value was not updated correctly, retrying...');
                            // Пробуем еще раз
                            streetInput.value = street;
                            streetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                            streetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                            streetInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                            
                            // Если все еще не обновилось, пробуем через Livewire API
                            if (streetInput.value !== street && typeof Livewire !== 'undefined') {
                                console.log('Filament Autocomplete: Trying to update street via Livewire API');
                                // Найдем компонент через поиск всех компонентов
                                try {
                                const allComponents = Livewire.all();
                                for (let component of allComponents) {
                                    if (component && component.$wire) {
                                        try {
                                            // Проверяем, что компонент имеет свойство address перед попыткой установки
                                            const hasAddress = component.$wire.get('address') !== undefined;
                                            if (hasAddress) {
                                                component.$wire.set('address.street', street);
                                                console.log('Filament Autocomplete: Updated street via Livewire API (fallback)');
                                                break;
                                            }
                                        } catch (e) {
                                            // Игнорируем ошибки (компонент может не иметь свойства address)
                                        }
                                    }
                                }
                                } catch (e) {
                                    console.warn('Filament Autocomplete: Failed to update street via Livewire API (fallback)', e);
                                }
                            }
                        }
                    }, 200);
                } else {
                    console.warn('Filament Autocomplete: streetInput not found');
                    // Пробуем найти поле еще раз, исключая street_place_id
                    const allInputs = form?.querySelectorAll('input[type="text"], input:not([type])');
                    let streetField = null;
                    if (allInputs) {
                        for (let input of allInputs) {
                            const name = input.name || '';
                            const id = input.id || '';
                            // Ищем поле street, но не street_place_id
                            if ((name.includes('[street]') || name.includes('.street') || name === 'street') && 
                                !name.includes('street_place_id') && !id.includes('street_place_id')) {
                                streetField = input;
                                console.log('Filament Autocomplete: Found street field on retry', {
                                    name: name,
                                    id: id
                                });
                                break;
                            }
                        }
                    }
                    
                    if (streetField) {
                        streetField.value = street;
                        streetField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        streetField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                        streetField.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                    } else {
                        console.warn('Filament Autocomplete: street field not found even on retry');
                        // Выводим все текстовые поля для отладки
                        const allTextInputs = form?.querySelectorAll('input[type="text"], input:not([type])');
                        const allInputsInfo = Array.from(allTextInputs || []).map(input => ({
                            name: input.name,
                            id: input.id,
                            value: input.value,
                            placeholder: input.placeholder,
                            labels: Array.from(form?.querySelectorAll('label') || []).filter(label => {
                                const forAttr = label.getAttribute('for');
                                return forAttr === input.id || label.textContent.includes('Вулиця') || label.textContent.includes('Улица');
                            }).map(l => l.textContent.trim())
                        }));
                        console.log('Filament Autocomplete: All text inputs in form', allInputsInfo);
                        
                        // Пробуем найти поле по label "Вулиця" или "Улица" (но не "Вулиця (Київ)")
                        const labels = form?.querySelectorAll('label');
                        if (labels) {
                            for (let label of labels) {
                                const labelText = label.textContent.trim();
                                const labelTextLower = labelText.toLowerCase();
                                // Ищем label с "Вулиця" или "Улица", но не "Вулиця (Київ)" или "street_place"
                                if ((labelTextLower.includes('вулиця') || labelTextLower.includes('улица')) && 
                                    !labelTextLower.includes('київ') && !labelTextLower.includes('киев') &&
                                    !labelTextLower.includes('street_place')) {
                                    console.log('Filament Autocomplete: Found label for street field', labelText);
                                    
                                    const forAttr = label.getAttribute('for');
                                    if (forAttr) {
                                        const targetInput = form?.querySelector('input#' + forAttr);
                                        if (targetInput && !targetInput.name.includes('street_place_id') && !targetInput.id.includes('street_place_id')) {
                                            console.log('Filament Autocomplete: Found street field by label (for attribute)', {
                                                labelText: labelText,
                                                forAttr: forAttr,
                                                inputName: targetInput.name,
                                                inputId: targetInput.id
                                            });
                                            targetInput.value = street;
                                            targetInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                                            targetInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                            targetInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                                            break;
                                        }
                                    }
                                    
                                    // Если нет for атрибута, ищем input внутри родительского элемента label или следующий input
                                    let parent = label ? label.parentElement : null;
                                    let foundInput = null;
                                    
                                    // Ищем в родительском элементе
                                    if (parent) {
                                        const inputInParent = parent.querySelector('input[type="text"], input:not([type])');
                                        if (inputInParent && !inputInParent.name.includes('street_place_id') && !inputInParent.id.includes('street_place_id')) {
                                            foundInput = inputInParent;
                                            console.log('Filament Autocomplete: Found street field by label (parent element)', {
                                                labelText: labelText,
                                                inputName: inputInParent.name,
                                                inputId: inputInParent.id
                                            });
                                        }
                                    }
                                    
                                    // Если не нашли в родителе, ищем следующий input после label
                                    if (!foundInput) {
                                        let nextSibling = label.nextElementSibling;
                                        while (nextSibling && nextSibling !== form) {
                                            if (nextSibling.tagName === 'INPUT' && !nextSibling.name.includes('street_place_id') && !nextSibling.id.includes('street_place_id')) {
                                                foundInput = nextSibling;
                                                console.log('Filament Autocomplete: Found street field by label (next sibling)', {
                                                    labelText: labelText,
                                                    inputName: nextSibling.name,
                                                    inputId: nextSibling.id
                                                });
                                                break;
                                            }
                                            nextSibling = nextSibling.nextElementSibling;
                                        }
                                    }
                                    
                                    // Если все еще не нашли, ищем в соседних элементах через общий родитель
                                    if (!foundInput && parent) {
                                        const allInputsInParent = parent.querySelectorAll('input[type="text"], input:not([type])');
                                        for (let input of allInputsInParent) {
                                            if (!input.name.includes('street_place_id') && !input.id.includes('street_place_id')) {
                                                foundInput = input;
                                                console.log('Filament Autocomplete: Found street field by label (sibling in parent)', {
                                                    labelText: labelText,
                                                    inputName: input.name,
                                                    inputId: input.id
                                                });
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Обновляем найденное поле
                                    if (foundInput) {
                                        foundInput.value = street;
                                        foundInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                                        foundInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                        foundInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                                        
                                        // Также пробуем обновить через Alpine.js, если доступен
                                        if (typeof Alpine !== 'undefined' && foundInput.hasAttribute('x-model')) {
                                            const modelName = foundInput.getAttribute('x-model');
                                            console.log('Filament Autocomplete: Found x-model on street field', modelName);
                                            // Alpine.js должен автоматически обновить значение через x-model
                                        }
                                        
                                        // Проверяем, что значение обновилось
                                        setTimeout(function() {
                                            console.log('Filament Autocomplete: street field value after update', foundInput.value);
                                            if (foundInput.value !== street) {
                                                console.warn('Filament Autocomplete: street field value was not updated correctly, retrying...');
                                                foundInput.value = street;
                                                foundInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                                                foundInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                            }
                                        }, 100);
                                    } else {
                                        console.warn('Filament Autocomplete: Label found but input not found', {
                                            labelText: labelText,
                                            labelHtml: label.outerHTML.substring(0, 200),
                                            parentHtml: parent ? parent.outerHTML.substring(0, 300) : null
                                        });
                                        
                                        // Пробуем найти поле через поиск по всем input в форме, используя позицию label
                                        const allInputs = form?.querySelectorAll('input[type="text"], input:not([type])');
                                        if (allInputs) {
                                            const labelIndex = Array.from(labels).indexOf(label);
                                            // Ищем input, который находится рядом с этим label по индексу
                                            const inputsArray = Array.from(allInputs);
                                            // Исключаем street_place_id
                                            const streetInputs = inputsArray.filter(input => 
                                                !input.name.includes('street_place_id') && 
                                                !input.id.includes('street_place_id')
                                            );
                                            
                                            // Пробуем найти input, который находится после label "Вулиця (Київ)" но перед следующим label
                                            const streetPlaceLabel = Array.from(labels).find(l => 
                                                l.textContent.toLowerCase().includes('вулиця') && 
                                                l.textContent.toLowerCase().includes('київ')
                                            );
                                            
                                            if (streetPlaceLabel) {
                                                const streetPlaceInput = form?.querySelector('input#' + streetPlaceLabel.getAttribute('for'));
                                                if (streetPlaceInput && streetPlaceInput.parentElement) {
                                                    // Ищем следующий input после street_place_id
                                                    let currentElement = streetPlaceInput.parentElement;
                                                    while (currentElement) {
                                                        const nextInput = currentElement.querySelector('input[type="text"], input:not([type])');
                                                        if (nextInput && !nextInput.name.includes('street_place_id') && !nextInput.id.includes('street_place_id')) {
                                                            console.log('Filament Autocomplete: Found street field after street_place_id', {
                                                                inputName: nextInput.name,
                                                                inputId: nextInput.id
                                                            });
                                                            nextInput.value = street;
                                                            nextInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                                                            nextInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                                            nextInput.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                                                            break;
                                                        }
                                                        currentElement = currentElement.nextElementSibling;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                if (houseInput && streetNumber) {
                    houseInput.value = streetNumber;
                    houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                if (cityInput) {
                    cityInput.value = city;
                    cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                
                const location = place.geometry.location;
                const lat = location.lat();
                const lng = location.lng();
                
                console.log('Filament Autocomplete: Extracted coordinates', { lat: lat, lng: lng });
                console.log('Filament Autocomplete: Checking Livewire availability', {
                    hasLivewire: typeof Livewire !== 'undefined',
                    hasForm: !!form,
                    formElement: form
                });
                
                // Используем Livewire API для обновления значений, если доступен
                if (typeof Livewire !== 'undefined' && form) {
                    // Находим wire:id формы
                    const wireId = form.getAttribute('wire:id');
                    console.log('Filament Autocomplete: Form wire:id', wireId);
                    
                    if (wireId) {
                        try {
                            console.log('Filament Autocomplete: Attempting to find Livewire component', wireId);
                            const livewireComponent = Livewire.find(wireId);
                            console.log('Filament Autocomplete: Livewire component found', !!livewireComponent);
                            
                            if (livewireComponent) {
                                console.log('Filament Autocomplete: Updating coordinates via Livewire', {
                                    lat: lat,
                                    lng: lng,
                                    wireId: wireId
                                });
                                
                                // Получаем текущий адрес
                                const currentAddress = livewireComponent.get('address') || {};
                                console.log('Filament Autocomplete: Current address before update', currentAddress);
                                
                                // ВАЖНО: Обновляем координаты одновременно, чтобы afterStateUpdated сработал с обеими координатами
                                // Используем setTimeout с минимальной задержкой, чтобы оба set() выполнились в одном цикле
                                setTimeout(function() {
                                    livewireComponent.set('address.latitude', lat.toString());
                                    livewireComponent.set('address.longitude', lng.toString());
                                    console.log('Filament Autocomplete: Set coordinates simultaneously', {
                                        lat: lat.toString(),
                                        lng: lng.toString()
                                    });
                                }, 0);
                                
                                // Обновляем остальные поля
                                if (place.formatted_address) {
                                    livewireComponent.set('address.formatted_address', place.formatted_address);
                                }
                                if (street) {
                                    livewireComponent.set('address.street', street);
                                    console.log('Filament Autocomplete: Set address.street to', street);
                                    
                                    // Проверяем, что значение обновилось
                                    setTimeout(function() {
                                        const updatedStreet = livewireComponent.get('address.street');
                                        console.log('Filament Autocomplete: address.street after Livewire update', updatedStreet);
                                        if (updatedStreet !== street) {
                                            console.warn('Filament Autocomplete: address.street was not updated correctly via Livewire');
                                        }
                                    }, 200);
                                }
                                if (streetNumber) {
                                    livewireComponent.set('address.house', streetNumber);
                                }
                                if (city) {
                                    livewireComponent.set('address.city', city);
                                }
                                
                                // Проверяем, что координаты установились
                                setTimeout(function() {
                                    const updatedAddress = livewireComponent.get('address') || {};
                                    console.log('Filament Autocomplete: Address after update', {
                                        latitude: updatedAddress.latitude,
                                        longitude: updatedAddress.longitude,
                                        full_address: updatedAddress
                                    });
                                    
                                    // Триггерим обновление поля доставки
                                    const updateKey = 'coords_' + lat + '_' + lng + '_' + Date.now();
                                    console.log('Filament Autocomplete: Setting delivery_coords_trigger to', updateKey);
                                    livewireComponent.set('delivery_coords_trigger', updateKey);
                                    
                                    // Проверяем, что триггер установился
                                    setTimeout(function() {
                                        const trigger = livewireComponent.get('delivery_coords_trigger');
                                        console.log('Filament Autocomplete: delivery_coords_trigger after set', trigger);
                                    }, 100);
                                }, 300);
                                
                                return; // Выходим, так как Livewire обновит все автоматически
                            } else {
                                console.warn('Filament Autocomplete: Livewire component not found for wireId', wireId);
                            }
                        } catch (e) {
                            console.error('Filament Autocomplete: Error updating via Livewire:', e);
                        }
                    } else {
                        console.warn('Filament Autocomplete: No wire:id found on form');
                    }
                } else {
                    console.warn('Filament Autocomplete: Livewire not available or form not found', {
                        hasLivewire: typeof Livewire !== 'undefined',
                        hasForm: !!form
                    });
                }
                
                // Обновляем через события DOM напрямую (основной способ для Filament)
                console.log('Filament Autocomplete: Updating inputs via DOM events');
                console.log('Filament Autocomplete: Found inputs', {
                    latitudeInput: !!latitudeInput,
                    longitudeInput: !!longitudeInput,
                    deliveryTrigger: !!deliveryTrigger
                });
                
                // ВАЖНО: Обновляем координаты одновременно, чтобы afterStateUpdated сработал с обеими координатами
                if (latitudeInput && longitudeInput) {
                    console.log('Filament Autocomplete: Updating coordinates simultaneously', { lat: lat, lng: lng });
                    
                    // Устанавливаем значения одновременно
                    latitudeInput.value = lat;
                    longitudeInput.value = lng;
                    
                    // Триггерим события одновременно
                    const inputEventLat = new Event('input', { bubbles: true, cancelable: true });
                    const inputEventLng = new Event('input', { bubbles: true, cancelable: true });
                    latitudeInput.dispatchEvent(inputEventLat);
                    longitudeInput.dispatchEvent(inputEventLng);
                    
                    // Также пробуем change события
                    const changeEventLat = new Event('change', { bubbles: true, cancelable: true });
                    const changeEventLng = new Event('change', { bubbles: true, cancelable: true });
                    latitudeInput.dispatchEvent(changeEventLat);
                    longitudeInput.dispatchEvent(changeEventLng);
                    
                    // Проверяем, что значения установились
                    setTimeout(function() {
                        if (latitudeInput.value !== lat || longitudeInput.value !== lng) {
                            console.warn('Filament Autocomplete: Coordinates were lost, restoring...', {
                                expectedLat: lat,
                                actualLat: latitudeInput.value,
                                expectedLng: lng,
                                actualLng: longitudeInput.value
                            });
                            latitudeInput.value = lat;
                            longitudeInput.value = lng;
                            latitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                            longitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        }
                    }, 50);
                } else {
                    // Fallback: обновляем по отдельности, если не нашли оба поля
                    if (latitudeInput) {
                        console.log('Filament Autocomplete: Updating latitude input directly', lat);
                        latitudeInput.value = lat;
                        latitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        latitudeInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                    
                    if (longitudeInput) {
                        console.log('Filament Autocomplete: Updating longitude input directly', lng);
                        longitudeInput.value = lng;
                        longitudeInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        longitudeInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    }
                }
                
                if (formattedAddressInput) {
                    formattedAddressInput.value = place.formatted_address || '';
                    formattedAddressInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    formattedAddressInput.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                }
                
                // Триггерим обновление поля доставки через DOM
                // ВАЖНО: Обновляем delivery_coords_trigger ПОСЛЕ координат, чтобы гарантировать обновление
                if (deliveryTrigger && lat && lng) {
                    const updateKey = 'coords_' + lat + '_' + lng + '_' + Date.now();
                    console.log('Filament Autocomplete: Setting delivery_coords_trigger via DOM', updateKey);
                    const oldTrigger = deliveryTrigger.value;
                    deliveryTrigger.value = updateKey;
                    
                    // Всегда триггерим события для delivery_coords_trigger, даже если значение похоже
                    // Это гарантирует, что Filament пересчитает доставку при каждом изменении координат
                    deliveryTrigger.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                    deliveryTrigger.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                    // Также пробуем событие для Livewire
                    deliveryTrigger.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                    
                    // Дополнительно: принудительно фокусируем и разфокусируем поле для триггера
                    // Это иногда помогает Filament заметить изменение
                    if (document.activeElement !== deliveryTrigger) {
                        deliveryTrigger.focus();
                        setTimeout(function() {
                            deliveryTrigger.blur();
                        }, 10);
                    }
                    
                    // Также обновляем delivery_price_auto для пересчета shipping_price
                    // Делаем это с задержкой, чтобы координаты успели обновиться через afterStateUpdated
                    setTimeout(function() {
                        // Пробуем найти поле еще раз (на случай, если оно было создано динамически)
                        const deliveryPriceAutoField = deliveryPriceAuto || 
                                                      form?.querySelector('input[name*="delivery_price_auto"]') ||
                                                      form?.querySelector('input[name="delivery_price_auto"]') ||
                                                      form?.querySelector('input[id*="delivery_price_auto"]');
                        
                        if (deliveryPriceAutoField) {
                            const autoUpdateKey = 'auto_' + lat + '_' + lng + '_' + Date.now();
                            console.log('Filament Autocomplete: Updating delivery_price_auto', autoUpdateKey);
                            deliveryPriceAutoField.value = autoUpdateKey;
                            deliveryPriceAutoField.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                            deliveryPriceAutoField.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                            deliveryPriceAutoField.dispatchEvent(new CustomEvent('livewire:update', { bubbles: true }));
                            
                            // Проверяем, что значение обновилось
                            setTimeout(function() {
                                console.log('Filament Autocomplete: delivery_price_auto value after update', deliveryPriceAutoField.value);
                            }, 100);
                        } else {
                            console.warn('Filament Autocomplete: delivery_price_auto field not found');
                            // Выводим все скрытые поля для отладки
                            const allHiddenInputs = form?.querySelectorAll('input[type="hidden"]');
                            console.log('Filament Autocomplete: All hidden inputs', Array.from(allHiddenInputs || []).map(input => ({
                                name: input.name,
                                id: input.id,
                                value: input.value
                            })));
                        }
                    }, 300);
                }
                
                // Пробуем найти Livewire компонент через родительские элементы
                let livewireComponent = null;
                if (typeof Livewire !== 'undefined') {
                    // Ищем wire:id в родительских элементах
                    let parent = streetInput ? streetInput.parentElement : null;
                    let attempts = 0;
                    while (parent && attempts < 10) {
                        const parentWireId = parent.getAttribute('wire:id');
                        if (parentWireId) {
                            console.log('Filament Autocomplete: Found wire:id in parent', parentWireId);
                            try {
                                livewireComponent = Livewire.find(parentWireId);
                                if (livewireComponent) {
                                    console.log('Filament Autocomplete: Found Livewire component via parent', parentWireId);
                                    break;
                                }
                            } catch (e) {
                                console.log('Filament Autocomplete: Error finding Livewire component', e);
                            }
                        }
                        parent = parent ? parent.parentElement : null;
                        attempts++;
                    }
                    
                    // Если нашли компонент, обновляем через Livewire API как дополнительный способ
                    if (livewireComponent) {
                        console.log('Filament Autocomplete: Updating via Livewire API as fallback');
                        setTimeout(function() {
                            try {
                                // Обновляем координаты
                                livewireComponent.set('address.latitude', lat.toString());
                                livewireComponent.set('address.longitude', lng.toString());
                                
                                // Обновляем поле street через Livewire API - пробуем разные пути
                                if (street) {
                                    console.log('Filament Autocomplete: Setting address.street via Livewire API', street);
                                    const streetPaths = [
                                        'address.street',
                                        'data.address.street',
                                        'data.street',
                                        'street'
                                    ];
                                    
                                    let streetUpdated = false;
                                    for (let path of streetPaths) {
                                        try {
                                            livewireComponent.set(path, street);
                                            const updatedValue = livewireComponent.get(path);
                                            if (updatedValue === street) {
                                                console.log('Filament Autocomplete: Successfully updated street via Livewire using path', path, street);
                                                streetUpdated = true;
                                                break;
                                            }
                                        } catch (e) {
                                            // Пробуем следующий путь
                                        }
                                    }
                                    
                                    if (!streetUpdated) {
                                        // Пробуем напрямую через address.street
                                        try {
                                            livewireComponent.set('address.street', street);
                                            console.log('Filament Autocomplete: Set address.street via Livewire API (direct)', street);
                                        } catch (e) {
                                            console.warn('Filament Autocomplete: Failed to update street via Livewire API', e);
                                        }
                                    }
                                }
                                
                                // Обновляем остальные поля
                                if (streetNumber) {
                                    livewireComponent.set('address.house', streetNumber);
                                }
                                if (city) {
                                    livewireComponent.set('address.city', city);
                                }
                                if (place.formatted_address) {
                                    livewireComponent.set('address.formatted_address', place.formatted_address);
                                }
                                
                                // Обновляем триггер с небольшой задержкой
                                const updateKey = 'coords_' + lat + '_' + lng + '_' + Date.now();
                                livewireComponent.set('delivery_coords_trigger', updateKey);
                                console.log('Filament Autocomplete: Updated delivery_coords_trigger via Livewire', updateKey);
                                
                                // Проверяем, что поле street обновилось
                                setTimeout(function() {
                                    const updatedAddress = livewireComponent.get('address') || {};
                                    console.log('Filament Autocomplete: address.street after Livewire update', updatedAddress.street);
                                }, 200);
                                
                                // Дополнительно: отправляем событие для принудительного обновления
                                if (typeof Livewire !== 'undefined' && Livewire.emit) {
                                    Livewire.emit('address-coordinates-updated', {
                                        latitude: lat,
                                        longitude: lng,
                                        trigger: updateKey
                                    });
                                    console.log('Filament Autocomplete: Emitted address-coordinates-updated event');
                                }
                            } catch (e) {
                                console.log('Filament Autocomplete: Error updating via Livewire API', e);
                            }
                        }, 100);
                    } else if (typeof Livewire !== 'undefined' && Livewire.emit) {
                        // Если не нашли компонент, пробуем отправить глобальное событие
                        const updateKey = 'coords_' + lat + '_' + lng + '_' + Date.now();
                        Livewire.emit('address-coordinates-updated', {
                            latitude: lat,
                            longitude: lng,
                            trigger: updateKey
                        });
                        console.log('Filament Autocomplete: Emitted global address-coordinates-updated event', updateKey);
                    }
                }
                
                // Даем Filament время обработать изменения, затем проверяем результат
                setTimeout(function() {
                    console.log('Filament Autocomplete: Checking if values were updated', {
                        latitudeValue: latitudeInput ? latitudeInput.value : null,
                        longitudeValue: longitudeInput ? longitudeInput.value : null,
                        triggerValue: deliveryTrigger ? deliveryTrigger.value : null,
                        triggerUpdated: deliveryTrigger ? (deliveryTrigger.value && deliveryTrigger.value.startsWith('coords_')) : false
                    });
                }, 500);
                
                // Также пробуем обновить через Livewire после небольшой задержки
                if (typeof Livewire !== 'undefined' && form) {
                    const fallbackWireId = form.getAttribute('wire:id');
                    if (fallbackWireId) {
                        setTimeout(function() {
                            try {
                                const livewireComponent = Livewire.find(fallbackWireId);
                                if (livewireComponent) {
                                    // Проверяем, что координаты установились
                                    const checkAddress = livewireComponent.get('address') || {};
                                    console.log('Filament Autocomplete: Checking address after DOM update', {
                                        latitude: checkAddress.latitude,
                                        longitude: checkAddress.longitude
                                    });
                                    
                                    // Если координаты все еще не установлены, пробуем еще раз
                                    if (!checkAddress.latitude || !checkAddress.longitude) {
                                        console.log('Filament Autocomplete: Coordinates not set, retrying via Livewire');
                                        livewireComponent.set('address.latitude', lat.toString());
                                        livewireComponent.set('address.longitude', lng.toString());
                                        
                                        setTimeout(function() {
                                            const updateKey = 'coords_' + lat + '_' + lng + '_' + Date.now();
                                            livewireComponent.set('delivery_coords_trigger', updateKey);
                                        }, 200);
                                    }
                                }
                            } catch (e) {
                                console.error('Filament Autocomplete: Error in fallback Livewire update:', e);
                            }
                        }, 500);
                    }
                }
            });
            
            // Валидация: запрещаем ручной ввод
            streetInput.addEventListener('blur', function() {
                setTimeout(function() {
                    if (!isPlaceSelected && streetInput.value && streetInput.value !== selectedStreetValue) {
                        // Показываем предупреждение, но не блокируем (в админке можно разрешить ручной ввод)
                        // Можно добавить модальное окно, если нужно
                    }
                }, 300);
            });
            
            // Помечаем, что автокомплит инициализирован
            streetInput.dataset.autocompleteInitialized = 'true';
        } catch (e) {
            console.error('Error initializing Google Places Autocomplete in Filament:', e);
        }
    };
    
    // Глобальный колбэк для загрузки Google Maps
    window.__onGoogleMapsLoadedForFilament = function() {
        window.__googleMapsLoaded = true;
        setTimeout(window.initFilamentAddressAutocomplete, 500);
    };
    
    // Загружаем Google Maps API, если еще не загружен
    if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
        const script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?key={{ config("services.google_maps.key") }}&libraries=places,geometry&callback=__onGoogleMapsLoadedForFilament';
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    } else {
        // Если Google Maps уже загружен, сразу инициализируем
        window.__googleMapsLoaded = true;
    }
    
    // Ждем загрузки DOM
    console.log('Filament Autocomplete: Script loaded, setting up initialization');
    
    if (document.readyState === 'loading') {
        console.log('Filament Autocomplete: DOM is loading, waiting for DOMContentLoaded');
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Filament Autocomplete: DOMContentLoaded fired, initializing in 1000ms');
            setTimeout(window.initFilamentAddressAutocomplete, 1000);
        });
    } else {
        console.log('Filament Autocomplete: DOM already loaded, initializing in 1000ms');
        setTimeout(window.initFilamentAddressAutocomplete, 1000);
    }
    
    // Также слушаем события Livewire для обновления формы
    document.addEventListener('livewire:init', function() {
        console.log('Filament Autocomplete: livewire:init fired, initializing in 1000ms');
        setTimeout(window.initFilamentAddressAutocomplete, 1000);
    });
    
    // Слушаем события Filament для обновления формы
    document.addEventListener('filament:form-mounted', function() {
        console.log('Filament Autocomplete: filament:form-mounted fired, initializing in 500ms');
        setTimeout(window.initFilamentAddressAutocomplete, 500);
    });
</script>
@endif
