/**
 * Библиотека для автозаполнения адресов через Google Places API
 * Поддерживает ограничение поиска только по Киеву
 */

/**
 * Инициализация автозаполнения адреса
 * @param {Object} options - Параметры инициализации
 * @param {string} options.streetInputId - ID поля ввода улицы
 * @param {string} options.houseInputId - ID поля ввода дома (опционально)
 * @param {string} options.cityInputSelector - Селектор поля города (опционально, например 'input[name="city"]')
 * @param {boolean} options.kyivOnly - Ограничить поиск только Киевом (по умолчанию false)
 * @param {string} options.googleMapsKey - API ключ Google Maps (если не передан, берется из window.GOOGLE_MAPS_API_KEY)
 * @param {Function} options.onPlaceSelected - Callback при выборе адреса (опционально)
 */
function initAddressAutocomplete(options = {}) {
    const {
        streetInputId,
        houseInputId = null,
        cityInputSelector = null,
        kyivOnly = false,
        googleMapsKey = null,
        onPlaceSelected = null,
    } = options;

    if (!streetInputId) {
        console.error('address-autocomplete: streetInputId is required');
        return;
    }

    let autocompleteInitialized = false;
    let initAttempts = 0;
    const maxAttempts = 10;

    function initAutocomplete() {
        if (autocompleteInitialized) return;

        const streetInput = document.getElementById(streetInputId);
        const houseInput = houseInputId ? document.getElementById(houseInputId) : null;

        if (!streetInput) {
            initAttempts++;
            if (initAttempts < maxAttempts) {
                setTimeout(initAutocomplete, 500);
            }
            return;
        }

        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            initAttempts++;
            if (initAttempts < maxAttempts) {
                setTimeout(initAutocomplete, 500);
            }
            return;
        }

        // Настройки для автозаполнения
        const autocompleteOptions = {
            componentRestrictions: { country: 'ua' },
            types: ['address'],
        };

        // Если нужно ограничить только Киевом
        if (kyivOnly) {
            // Прямоугольник вокруг Киева
            const kyivBounds = new google.maps.LatLngBounds(
                new google.maps.LatLng(50.213273, 30.239440), // SW
                new google.maps.LatLng(50.590798, 30.825941)  // NE
            );
            autocompleteOptions.bounds = kyivBounds;
            autocompleteOptions.strictBounds = true;
        }

        try {
            const autocomplete = new google.maps.places.Autocomplete(streetInput, autocompleteOptions);

            // Если нужно ограничить только Киевом, настраиваем фильтр для dropdown
            if (kyivOnly) {
                setupKyivOnlyPacFilter();
            }

            autocomplete.addListener('place_changed', function () {
                const place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) return;

                const comps = place.address_components || [];
                let street = '';
                let streetNumber = '';
                let city = '';

                for (const c of comps) {
                    if (c.types.includes('locality')) {
                        city = c.long_name || '';
                    }
                    if (c.types.includes('route')) {
                        street = c.long_name;
                    }
                    if (c.types.includes('street_number')) {
                        streetNumber = c.long_name;
                    }
                }

                // Если ограничение по Киеву - проверяем город
                if (kyivOnly) {
                    if (!city || !/київ|kyiv|киев/i.test(city)) {
                        streetInput.value = '';
                        if (houseInput) houseInput.value = '';
                        alert('Доставка зараз працює тільки по Києву. Будь ласка, оберіть адресу в межах Києва.');
                        return;
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

                // Заполняем поле города, если оно есть
                if (city && cityInputSelector) {
                    const cityInput = document.querySelector(cityInputSelector);
                    if (cityInput && !cityInput.value) {
                        cityInput.value = city;
                        cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }

                // Триггерим событие для Alpine.js
                streetInput.dispatchEvent(new Event('input', { bubbles: true }));

                // Вызываем callback, если он передан
                if (onPlaceSelected && typeof onPlaceSelected === 'function') {
                    onPlaceSelected({
                        place,
                        street,
                        streetNumber,
                        city,
                        streetInput,
                        houseInput,
                    });
                }

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

    // Функция для фильтрации dropdown только по Киеву
    function setupKyivOnlyPacFilter() {
        function applyFilter() {
            const pacContainer = document.querySelector('.pac-container');
            if (!pacContainer) return;

            const items = pacContainer.querySelectorAll('.pac-item');
            items.forEach(item => {
                const text = (item.textContent || '').toLowerCase();

                const isKyiv =
                    text.includes('київ, україна') ||
                    text.includes('киев, украина') ||
                    text.includes('kyiv, ukraine');

                const hasRegion =
                    text.includes('київська область') ||
                    text.includes('киевская область') ||
                    text.includes('kyiv oblast') ||
                    text.includes('обл.');

                // показываем только те подсказки, где явно Киев и нет области
                if (!isKyiv || hasRegion) {
                    item.style.display = 'none';
                } else {
                    item.style.display = '';
                }
            });
        }

        function waitForPac() {
            const pacContainer = document.querySelector('.pac-container');
            if (!pacContainer) {
                setTimeout(waitForPac, 300);
                return;
            }

            // первый прогон
            applyFilter();

            // следим за изменениями и фильтруем каждое обновление
            const observer = new MutationObserver(applyFilter);
            observer.observe(pacContainer, { childList: true, subtree: true });
        }

        waitForPac();
    }

    // Функция для загрузки Google Maps API
    function loadGoogleMapsAPI() {
        if (typeof google !== 'undefined' && google.maps && google.maps.places) {
            initAutocomplete();
            return;
        }

        // Проверяем, не загружается ли уже скрипт
        if (document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]')) {
            // Скрипт уже есть, просто ждем его загрузки
            setTimeout(initAutocomplete, 1000);
            return;
        }

        // Получаем API ключ
        const apiKey = googleMapsKey || window.GOOGLE_MAPS_API_KEY || null;
        if (!apiKey) {
            console.error('address-autocomplete: Google Maps API key is required. Set window.GOOGLE_MAPS_API_KEY or pass googleMapsKey option.');
            return;
        }

        // Создаем уникальный callback для этого экземпляра
        const callbackName = `initAddressAutocompleteCallback_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        window[callbackName] = function() {
            initAutocomplete();
            delete window[callbackName];
        };

        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=${callbackName}`;
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
}

// Делаем доступным глобально для использования в Blade шаблонах
if (typeof window !== 'undefined') {
    window.initAddressAutocomplete = initAddressAutocomplete;
}

// Экспортируем для использования в других модулях (ES6)
export default {
    init: initAddressAutocomplete,
};

export { initAddressAutocomplete };
