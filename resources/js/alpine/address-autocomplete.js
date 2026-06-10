/**
 * Библиотека для автозаполнения адресов через Google Places API
 * Поддерживает ограничение поиска только по Киеву
 */

/**
 * Показывает модальное окно с сообщением об ошибке
 * @param {string} message - Текст сообщения
 */
const ADDRESS_TEXTS = {
    manual_input_not_allowed: 'Будь ласка, оберіть адресу зі списку Google. Ручне введення адреси не дозволено.',
    paste_not_allowed:        'Будь ласка, оберіть адресу зі списку Google. Вставка адреси не дозволена.',
    kyiv_only:                'Доставка зараз працює тільки по Києву. Будь ласка, оберіть адресу в межах Києва.',
};
// Хелпер: если захочешь переопределять тексты из Blade — можно через window.ADDRESS_TEXTS
function addressText(key, fallback = '') {
    const dict = (typeof window !== 'undefined' && window.ADDRESS_TEXTS) ? window.ADDRESS_TEXTS : ADDRESS_TEXTS;
    return dict[key] || fallback || key;
}
function showAddressErrorModal(message) {
    // Проверяем, существует ли уже модальное окно
    let modal = document.getElementById('address-error-modal');

    if (!modal) {
        // Создаем модальное окно, если его нет
        modal = document.createElement('div');
        modal.id = 'address-error-modal';
        modal.className = 'fixed inset-0 z-[100] flex items-center justify-center p-4 pointer-events-none';
        modal.style.display = 'none';
        modal.innerHTML = `
            <div class="fixed inset-0 bg-black/40 backdrop-blur-sm z-[100]" id="address-error-modal-backdrop"></div>
            <div class="relative bg-white rounded-[12px] shadow-xl z-[101] pointer-events-auto w-full max-w-[400px] p-6 md:p-8" id="address-error-modal-content">
                <button type="button" id="address-error-modal-close" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 transition-colors" aria-label="Закрыть">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex justify-center mb-4">

                </div>
                <div class="text-center">
                    <h3 class="text-lg md:text-xl font-semibold mb-2 text-red-600" id="address-error-modal-message"></h3>
                </div>
                <div class="mt-6 flex justify-center">
                    <button type="button" id="address-error-modal-ok" class="px-6 py-2 bg-[#FF7500] text-white rounded-lg hover:bg-orange-600 transition">
                        ОК
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Обработчики закрытия
        const closeModal = () => {
            modal.classList.remove('show');
            // Убираем модальное окно после завершения анимации
            setTimeout(() => {
                modal.style.display = 'none';
            }, 200);
        };

        document.getElementById('address-error-modal-close').addEventListener('click', closeModal);
        document.getElementById('address-error-modal-ok').addEventListener('click', closeModal);
        document.getElementById('address-error-modal-backdrop').addEventListener('click', closeModal);

        // Закрытие по Escape
        const escapeHandler = (e) => {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                closeModal();
            }
        };
        document.addEventListener('keydown', escapeHandler);
    }

    // Устанавливаем сообщение
    const messageEl = document.getElementById('address-error-modal-message');
    if (messageEl) {
        messageEl.textContent = message;
    }

    // Показываем модальное окно
    modal.style.display = 'flex';
    // Добавляем класс для анимации
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

function normalizeAddressValue(value) {
    return String(value || '')
        .toLowerCase()
        .replace(/[.,]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function isAddressSelectionMatch(currentValue, selectedValue) {
    const currentNorm = normalizeAddressValue(currentValue);
    const selectedNorm = normalizeAddressValue(selectedValue);

    if (!currentNorm || !selectedNorm) return false;

    return currentNorm === selectedNorm ||
        currentNorm.includes(selectedNorm) ||
        selectedNorm.includes(currentNorm);
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getAddressComponent(place, types) {
    const components = place?.address_components || [];

    for (const type of types) {
        const component = components.find((item) => item.types?.includes(type));
        if (component?.long_name) {
            return component.long_name;
        }
    }

    return '';
}

function uniqueAddressParts(parts) {
    const used = new Set();

    return parts.flatMap((part) => String(part || '').split(',')).map((part) => {
        const value = String(part || '').trim();
        const normalized = normalizeAddressValue(value);

        if (/^(місто\s+)?київ$|^(город\s+)?киев$|^kyiv$/iu.test(normalized)) {
            return 'Київ';
        }

        return value;
    }).filter((part) => {
        const value = String(part || '').trim();
        const key = normalizeAddressValue(value);

        if (!key || /^(ukraine|украина|україна)$/iu.test(key) || used.has(key)) {
            return false;
        }

        used.add(key);
        return true;
    });
}

function buildAddressHint(prediction, place) {
    const secondary = prediction?.structured_formatting?.secondary_text || '';
    const district = getAddressComponent(place, [
        'sublocality_level_1',
        'sublocality',
        'neighborhood',
        'administrative_area_level_3',
    ]);
    const locality = getAddressComponent(place, ['locality', 'postal_town']);
    const adminArea = getAddressComponent(place, ['administrative_area_level_1']);
    const highlightedParts = uniqueAddressParts([district, locality, adminArea]);
    const secondaryNorm = normalizeAddressValue(secondary);
    const highlighted = highlightedParts
        .filter((part) => !secondaryNorm.includes(normalizeAddressValue(part)))
        .join(', ');

    return {
        highlighted,
        secondary: secondary || place?.formatted_address || '',
    };
}

/**
 * Загружает зависимости для фильтрации по зонам доставки (Google Maps API, jQuery, map-cart.js)
 * @param {Function} callback - Функция, которая будет вызвана после загрузки всех зависимостей
 */
function loadDeliveryZoneDependencies(callback) {
    let googleMapsLoaded = false;
    let jqueryLoaded = false;
    let mapCartLoaded = false;
    let allChecksDone = false;

    // Проверяем, что уже загружено
    if (typeof google !== 'undefined' && google.maps && google.maps.places && google.maps.geometry) {
        googleMapsLoaded = true;
    }
    if (typeof $ !== 'undefined' && typeof jQuery !== 'undefined') {
        jqueryLoaded = true;
    }
    if (typeof window.deliveryAreas !== 'undefined') {
        mapCartLoaded = true;
    }

    // Если все уже загружено, вызываем callback
    if (googleMapsLoaded && mapCartLoaded) {
        callback();
        return;
    }

    // Функция проверки готовности
    function checkAllLoaded() {
        if (allChecksDone) return;

        // Google Maps API обязателен
        if (!googleMapsLoaded) return;

        // jQuery не обязателен, если deliveryAreas уже доступен
        // map-cart.js обязателен (через deliveryAreas)
        if (typeof window.deliveryAreas !== 'undefined') {
            mapCartLoaded = true;
            allChecksDone = true;
            callback();
        } else {
            // Ждем загрузки map-cart.js (максимум 10 секунд)
            let attempts = 0;
            const maxAttempts = 50;
            const interval = setInterval(function() {
                attempts++;
                if (typeof window.deliveryAreas !== 'undefined') {
                    mapCartLoaded = true;
                    allChecksDone = true;
                    clearInterval(interval);
                    callback();
                } else if (attempts >= maxAttempts) {
                    clearInterval(interval);
                    allChecksDone = true;
                    // console.warn('map-cart.js не загрузился, фильтрация по зонам недоступна');
                    callback(); // Вызываем callback даже если map-cart.js не загрузился
                }
            }, 200);
        }
    }

    // Загружаем Google Maps API (только если еще не загружен)
    if (!googleMapsLoaded) {
        // Проверяем, не загружается ли уже скрипт
        const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
        if (existingScript) {
            // Скрипт уже есть, ждем его загрузки
            const checkInterval = setInterval(function() {
                if (typeof google !== 'undefined' && google.maps && google.maps.places && google.maps.geometry) {
                    googleMapsLoaded = true;
                    clearInterval(checkInterval);
                    checkAllLoaded();
                }
            }, 200);

            // Таймаут на случай, если скрипт не загрузится
            setTimeout(function() {
                clearInterval(checkInterval);
                if (!googleMapsLoaded) {
                    // console.warn('Google Maps API не загрузился в течение 10 секунд');
                    callback();
                }
            }, 10000);
        } else {
            // Загружаем Google Maps API
            const googleScript = document.createElement('script');
            googleScript.src = `https://maps.googleapis.com/maps/api/js?key=${window.GOOGLE_MAPS_API_KEY || ''}&libraries=places,geometry`;
            googleScript.async = true;
            googleScript.defer = true;
            googleScript.onload = function() {
                googleMapsLoaded = true;
                checkAllLoaded();
            };
            googleScript.onerror = function() {
                // console.error('Ошибка загрузки Google Maps API');
                googleMapsLoaded = false;
                callback(); // Вызываем callback даже при ошибке
            };
            document.head.appendChild(googleScript);
        }
    } else {
        checkAllLoaded();
    }

    // Загружаем jQuery (не обязателен, но может быть нужен для map-cart.js)
    if (!jqueryLoaded && typeof window.deliveryAreas === 'undefined') {
        // Проверяем, не загружается ли уже jQuery
        const existingJQuery = document.querySelector('script[src*="jquery"]');
        if (existingJQuery) {
            // jQuery уже загружается, ждем
            const checkJQueryInterval = setInterval(function() {
                if (typeof $ !== 'undefined' && typeof jQuery !== 'undefined') {
                    jqueryLoaded = true;
                    clearInterval(checkJQueryInterval);
                }
            }, 200);

            setTimeout(function() {
                clearInterval(checkJQueryInterval);
            }, 5000);
        } else {
            // Загружаем jQuery
            const jqueryScript = document.createElement('script');
            jqueryScript.src = 'https://code.jquery.com/jquery-3.7.1.min.js';
            jqueryScript.onload = function() {
                jqueryLoaded = true;
            };
            jqueryScript.onerror = function() {
                // console.warn('Ошибка загрузки jQuery, продолжаем без него');
            };
            document.head.appendChild(jqueryScript);
        }
    }
}

/**
 * Создает функцию проверки зоны доставки на основе deliveryAreas
 * @param {Object} map - Объект карты Google Maps
 * @returns {Function|null} Функция проверки зоны или null
 */
function createDeliveryZoneChecker(map) {
    if (typeof window.deliveryAreas === 'undefined' || !map) {
        return null;
    }

    const deliveryAreas = window.deliveryAreas;

    // Создаем полигоны зон доставки, если они еще не созданы
    for (const key in deliveryAreas) {
        if (!deliveryAreas[key].polygon && deliveryAreas[key].area) {
            try {
                deliveryAreas[key].polygon = new google.maps.Polygon({
                    path: deliveryAreas[key].area,
                    geodesic: true,
                    map: null, // Не показываем на карте
                });
            } catch (e) {
                // console.error('Ошибка создания полигона зоны доставки:', e);
            }
        }
    }

    // Используем глобальную функцию или создаем свою
    if (typeof window.resolveAreaByLatLng !== 'undefined') {
        return window.resolveAreaByLatLng;
    } else {
        return function(latLng) {
            if (typeof google === 'undefined' || !google.maps || !google.maps.geometry || !google.maps.geometry.poly) {
                return null;
            }
            for (const key in deliveryAreas) {
                if (deliveryAreas[key].polygon &&
                    google.maps.geometry.poly.containsLocation(latLng, deliveryAreas[key].polygon)) {
                    return deliveryAreas[key];
                }
            }
            return null;
        };
    }
}

/**
 * Инициализация автозаполнения адреса
 * @param {Object} options - Параметры инициализации
 * @param {string} options.streetInputId - ID поля ввода улицы
 * @param {string} options.houseInputId - ID поля ввода дома (опционально)
 * @param {string} options.cityInputSelector - Селектор поля города (опционально, например 'input[name="city"]')
 * @param {boolean} options.kyivOnly - Ограничить поиск только Киевом (по умолчанию false)
 * @param {string} options.googleMapsKey - API ключ Google Maps (если не передан, берется из window.GOOGLE_MAPS_API_KEY)
 * @param {Function} options.onPlaceSelected - Callback при выборе адреса (опционально)
 * @param {Function} options.checkDeliveryZone - Функция для проверки попадания адреса в зону доставки (опционально)
 *   Принимает google.maps.LatLng и возвращает объект зоны или null
 * @param {Object} options.map - Объект карты Google Maps (необходим для работы с PlacesService, опционально)
 * @param {boolean} options.filterByDeliveryZone - Фильтровать результаты по зонам доставки (по умолчанию false)
 *   Если true, автоматически загрузит зависимости и создаст скрытую карту если нужно
 */
function initAddressAutocomplete(options = {}) {
    const {
        streetInputId,
        houseInputId = null,
        cityInputSelector = null,
        kyivOnly = false,
        googleMapsKey = null,
        onPlaceSelected = null,
        checkDeliveryZone = null,
        map = null,
        filterByDeliveryZone = false,
    } = options;

    if (!streetInputId) {
        // console.error('address-autocomplete: streetInputId is required');
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
            // Если нужна фильтрация по зонам доставки, загружаем зависимости и инициализируем
            if (filterByDeliveryZone) {
                loadDeliveryZoneDependencies(function() {
                    // Создаем скрытую карту, если не передана
                    let actualMap = map;
                    if (!actualMap) {
                        const hiddenMapDiv = document.createElement('div');
                        hiddenMapDiv.id = `hidden-map-${streetInputId}`;
                        hiddenMapDiv.style.cssText = 'display: none; width: 1px; height: 1px; position: absolute; left: -9999px;';
                        document.body.appendChild(hiddenMapDiv);

                        try {
                            actualMap = new google.maps.Map(hiddenMapDiv, {
                                center: { lat: 50.4590851, lng: 30.4182548 },
                                zoom: 11,
                                disableDefaultUI: true,
                            });
                        } catch (e) {
                            // console.error('Ошибка создания скрытой карты:', e);
                            // Fallback: без фильтрации по зонам
                            initStandardAutocomplete();
                            return;
                        }
                    }

                    // Создаем функцию проверки зон
                    const actualCheckDeliveryZone = checkDeliveryZone || createDeliveryZoneChecker(actualMap);

                    if (actualCheckDeliveryZone && actualMap) {
                        initAutocompleteWithDeliveryZoneFilter(streetInput, houseInput, cityInputSelector, kyivOnly, onPlaceSelected, actualCheckDeliveryZone, actualMap);
                    } else {
                        // console.warn('Не удалось создать функцию проверки зон, используем стандартное автозаполнение');
                        initStandardAutocomplete();
                    }
                });
                return;
            }

            // Стандартная инициализация без фильтрации по зонам
            initStandardAutocomplete();
        } catch (e) {
            // console.error('Error initializing Google Places Autocomplete:', e);
        }
    }

    function initStandardAutocomplete() {
        const streetInput = document.getElementById(streetInputId);
        const houseInput = houseInputId ? document.getElementById(houseInputId) : null;

        if (!streetInput) {
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

            // Сохраняем выбранное значение улицы из Google Places
            let selectedStreetValue = '';
            let isPlaceSelected = false;
            let isSelectingFromGoogle = false; // Флаг, что идет процесс выбора из Google Places
            let lastGoogleSelectionAt = 0;

            autocomplete.addListener('place_changed', function () {
                // Устанавливаем флаг, что выбор происходит из Google Places
                isSelectingFromGoogle = true;
                const place = autocomplete.getPlace();
                if (!place || !place.geometry || !place.geometry.location) return;

                const comps = place.address_components || [];
                let street = '';
                let streetNumber = '';
                let city = '';
                let administrativeArea = '';

                for (const c of comps) {
                    if (c.types.includes('locality')) {
                        city = c.long_name || '';
                    }
                    if (c.types.includes('administrative_area_level_1')) {
                        administrativeArea = c.long_name || '';
                    }
                    if (c.types.includes('route')) {
                        street = c.long_name;
                    }
                    if (c.types.includes('street_number')) {
                        streetNumber = c.long_name;
                    }
                }

                // Если ограничение по Киеву - проверяем город
        /*        if (kyivOnly) {
                    if (!city || !/київ|kyiv|киев/i.test(city)) {
                        streetInput.value = '';
                        if (houseInput) houseInput.value = '';
                        selectedStreetValue = '';
                        isPlaceSelected = false;
                        showAddressErrorModal('Доставка зараз працює тільки по Києву. Будь ласка, оберіть адресу в межах Києва.');
                        return;
                    }
                }*/

                // Закрываем dropdown ПЕРЕД изменением значения
                const pacContainer = document.querySelector('.pac-container');
                if (pacContainer) {
                    pacContainer.style.display = 'none';
                }

                // Формируем строку города: если не Киев, добавляем область
                let cityValue = '';
                if (city) {
                    // Проверяем, является ли это Киевом
                    const isKyiv = city.toLowerCase().includes('київ') ||
                                  city.toLowerCase().includes('киев') ||
                                  city.toLowerCase().includes('kyiv');

                    if (isKyiv) {
                        cityValue = 'Київ';
                    } else {
                        // Для других городов добавляем область
                        cityValue = city;
                        if (administrativeArea) {
                            cityValue += ' ' + administrativeArea;
                        }
                    }
                }

                // Формируем полный адрес для поля улицы
                let fullStreetValue = street;
                if (cityValue && cityValue !== 'Київ') {
                    // Если город не Киев, добавляем его к адресу улицы
                    fullStreetValue = fullStreetValue + (fullStreetValue ? ', ' : '') + cityValue;
                }

                // Заполняем поле улицы (полный адрес, если не Киев)
                if (fullStreetValue) {
                    streetInput.value = fullStreetValue;
                    selectedStreetValue = fullStreetValue;
                    isPlaceSelected = true;
                    lastGoogleSelectionAt = Date.now();
                    // Сбрасываем флаг выбора после небольшой задержки, чтобы blur не сработал раньше
                    setTimeout(() => {
                        isSelectingFromGoogle = false;
                    }, 100);
                } else {
                    // Если улица не найдена, сбрасываем флаг
                    isSelectingFromGoogle = false;
                }

                // Заполняем поле дома, если номер дома есть
                if (streetNumber && houseInput) {
                    houseInput.value = streetNumber;
                    houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                // Заполняем поле города, если оно есть
                if (cityValue && cityInputSelector) {
                    const cityInput = document.querySelector(cityInputSelector);
                    if (cityInput) {
                        cityInput.value = cityValue;
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

                // Раньше здесь мы убирали фокус с поля улицы и переводили его на поле дома.
                // На Android это могло приводить к мгновенному закрытию клавиатуры.
                // Теперь НИЧЕГО не трогаем с фокусом: пользователь сам перейдет в следующее поле.
                //
                // Дополнительно просто скрываем dropdown на случай, если он все еще виден.
                setTimeout(function() {
                    const pacContainer = document.querySelector('.pac-container');
                    if (pacContainer) {
                        pacContainer.style.display = 'none';
                    }
                }, 50);
            });

            // Отслеживаем ручной ввод и запрещаем сохранение значения, не выбранного из Google Places
            let isTypingForSearch = false; // Флаг, что пользователь вводит для поиска в Google Places

            // Отслеживаем клики по элементам из списка Google Places
            document.addEventListener('click', function(e) {
                // Если клик по элементу из списка Google Places
                const pacItem = e.target.closest('.pac-item');
                if (pacItem) {
                    isSelectingFromGoogle = true;
                    // Сбрасываем флаг через 500ms, если выбор не произошел
                    setTimeout(() => {
                        if (isSelectingFromGoogle) {
                            isSelectingFromGoogle = false;
                        }
                    }, 500);
                }
            }, true); // Используем capture phase для раннего перехвата

            // Разрешаем ввод для поиска, но запрещаем сохранение значения, не выбранного из списка
            streetInput.addEventListener('input', function(e) {
                const currentValue = e.target.value;

                if (isSelectingFromGoogle) {
                    return;
                }

                // Если адрес был выбран из Google Places
                if (isPlaceSelected && selectedStreetValue) {
                    // Если пользователь начал редактировать выбранный адрес
                    if (!isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        // Сбрасываем флаг выбора, разрешаем ввод для нового поиска
                        isPlaceSelected = false;
                        selectedStreetValue = '';
                        isTypingForSearch = true;
                        isSelectingFromGoogle = false;
                    }
                } else {
                    // Пользователь вводит для поиска
                    isTypingForSearch = true;
                }
            });

            // При потере фокуса проверяем, что значение было выбрано из Google Places
            streetInput.addEventListener('blur', function(e) {
                // Добавляем задержку, чтобы дать время событию place_changed сработать
                setTimeout(() => {
                    // Если идет процесс выбора из Google Places, не валидируем
                    if (isSelectingFromGoogle) {
                        return;
                    }

                    const currentValue = e.target.value;
                    if (!currentValue || currentValue.trim() === '') {
                        isTypingForSearch = false;
                        return;
                    }

                    if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        isPlaceSelected = true;
                        return;
                    }

                    if (Date.now() - lastGoogleSelectionAt < 1200) {
                        return;
                    }

                    // Если адрес был выбран из Google Places, но значение изменилось - восстанавливаем
                    if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        e.target.value = selectedStreetValue;
                        return;
                    }

                    // Если адрес не был выбран из Google Places, но есть значение - очищаем
                    if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                        e.target.value = '';
                        showAddressErrorModal(addressText('manual_input_not_allowed'));
                    }

                    isTypingForSearch = false;
                }, 300); // Задержка 300ms, чтобы дать время place_changed сработать
            });

            // При вставке (paste) запрещаем, если адрес не выбран из Google Places
            streetInput.addEventListener('paste', function(e) {
                // Если адрес уже выбран из Google Places, разрешаем вставку только если это то же значение
                if (isPlaceSelected && selectedStreetValue) {
                    // Разрешаем вставку, но проверим после события paste
                    setTimeout(() => {
                        if (!isAddressSelectionMatch(streetInput.value, selectedStreetValue)) {
                            streetInput.value = selectedStreetValue;
                            showAddressErrorModal(addressText('manual_input_not_allowed'));     }
                    }, 0);
                } else {
                    // Если адрес не выбран, запрещаем вставку
                    e.preventDefault();
                    showAddressErrorModal(addressText('manual_input_not_allowed'));     }
            });

            // Добавляем валидацию при отправке формы
            const form = streetInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const shippingMethod = (form.querySelector('input[name="shipping_method"]')?.value || '').trim();
                    if (shippingMethod === 'pickup' || streetInput.disabled) {
                        return;
                    }

                    const currentValue = streetInput.value;

                    if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        isPlaceSelected = true;
                        return;
                    }

                    // Если адрес не был выбран из Google Places, но есть значение - блокируем отправку
                    if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                        e.preventDefault();
                        e.stopPropagation();
                        showAddressErrorModal(addressText('manual_input_not_allowed'));
                        streetInput.focus();
                        return false;
                    }

                    // Если адрес был выбран, но значение изменилось - блокируем отправку
                    if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        e.preventDefault();
                        e.stopPropagation();
                        streetInput.value = selectedStreetValue;
                        showAddressErrorModal(addressText('manual_input_not_allowed'));
                        streetInput.focus();
                        return false;
                    }
                }, true); // Используем capture phase для раннего перехвата
            }

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
            // console.error('Error initializing Google Places Autocomplete:', e);
        }
    }

    // Функция для инициализации автозаполнения с фильтрацией по зонам доставки
    function initAutocompleteWithDeliveryZoneFilter(streetInput, houseInput, cityInputSelector, kyivOnly, onPlaceSelected, checkDeliveryZone, map) {
        // Проверяем, что Google Maps API полностью загружен
        if (typeof google === 'undefined' || !google.maps || !google.maps.places || !google.maps.places.AutocompleteService) {
            // console.error('Google Maps API не полностью загружен для фильтрации по зонам');
            return;
        }

        // Проверяем, что map валиден
        if (!map) {
            // console.error('Map объект не передан для фильтрации по зонам');
            return;
        }

        try {
            const autocompleteService = new google.maps.places.AutocompleteService();
            const placesService = new google.maps.places.PlacesService(map);

        // Ограничиваем поиск только Киевом через bounds
        const kyivBounds = new google.maps.LatLngBounds(
            new google.maps.LatLng(50.213273, 30.239440), // SW
            new google.maps.LatLng(50.590798, 30.825941)  // NE
        );

        // Переменные для управления кастомным dropdown
        let customDropdown = null;
        let inputTimeout = null;
        let isProcessing = false;
        let currentQuery = '';
        let isClickingDropdown = false;
        let selectedStreetValue = '';
        let isPlaceSelected = false;
        let isSelectingFromGoogle = false; // Флаг, что идет процесс выбора из Google Places
        let lastGoogleSelectionAt = 0;

        // Создаем кастомный dropdown (без "powered by Google")
        function createCustomDropdown() {
            if (customDropdown && customDropdown.parentNode) {
                customDropdown.remove();
            }
            customDropdown = document.createElement('div');
            customDropdown.id = `custom-address-dropdown-${streetInputId}`;
            customDropdown.className = 'pac-container';
            customDropdown.setAttribute('data-custom', 'true');
            customDropdown.style.cssText = 'display: none; position: absolute; z-index: 10000; background: white; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15), 0 2px 4px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; font-family: Roboto, Arial, sans-serif; padding: 8px 0;';
            document.body.appendChild(customDropdown);

            // Скрываем стандартный "powered by Google" если он появится
            const hideGoogleLogo = function() {
                const pacLogos = document.querySelectorAll('.pac-logo, .pac-container:not([data-custom="true"])');
                pacLogos.forEach(logo => {
                    if (logo !== customDropdown) {
                        logo.style.display = 'none';
                    }
                });
            };
            setInterval(hideGoogleLogo, 100);

            customDropdown.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                isClickingDropdown = true;
            });

            customDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        function showCustomDropdown() {
            if (!customDropdown) createCustomDropdown();

            const inputRect = streetInput.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || inputRect.width;
            const preferredWidth = Math.max(inputRect.width, 420);
            const availableWidth = Math.max(inputRect.width, viewportWidth - inputRect.left - 16);
            const dropdownWidth = Math.min(preferredWidth, availableWidth);

            customDropdown.style.top = (inputRect.bottom + scrollTop) + 'px';
            customDropdown.style.left = (inputRect.left + scrollLeft) + 'px';
            customDropdown.style.width = dropdownWidth + 'px';
            customDropdown.style.display = 'block';
        }

        function hideCustomDropdown() {
            if (customDropdown) {
                customDropdown.style.display = 'none';
            }
        }

        // Функция для фильтрации предсказаний по зонам доставки
        function filterPredictionsByDeliveryZone(predictions, callback) {
            if (!predictions || predictions.length === 0) {
                callback([]);
                return;
            }

            const filtered = [];
            let checkedCount = 0;
            const total = predictions.length;

            predictions.forEach((prediction) => {
                placesService.getDetails(
                    {
                        placeId: prediction.place_id,
                        fields: ['geometry', 'address_components', 'formatted_address', 'place_id']
                    },
                    (place, status) => {
                        checkedCount++;

                        if (status === google.maps.places.PlacesServiceStatus.OK && place && place.geometry) {
                            // Используем переданную функцию проверки зоны доставки
                            const area = checkDeliveryZone(place.geometry.location);

                            if (area) {
                                filtered.push({
                                    prediction: prediction,
                                    place: place,
                                    area: area
                                });
                            }
                        }

                        if (checkedCount === total) {
                            callback(filtered);
                        }
                    }
                );
            });
        }

        // Перехватываем ввод текста
        streetInput.addEventListener('input', function() {
            clearTimeout(inputTimeout);

            const query = streetInput.value.trim();
            currentQuery = query;

            if (isSelectingFromGoogle) {
                return;
            }

            // Если адрес был выбран из списка и значение не изменилось, не показываем dropdown
            if (isPlaceSelected && selectedStreetValue && isAddressSelectionMatch(query, selectedStreetValue)) {
                return;
            }

            if (query.length < 2) {
                hideCustomDropdown();
                // НЕ сбрасываем isPlaceSelected, если пользователь случайно удалил символ
                // isPlaceSelected = false;
                // selectedStreetValue = '';
                return;
            }

            // Если адрес был выбран, но пользователь начал редактировать, сбрасываем флаг
            // Но только если изменение значительное (не просто удаление пробела в конце)
            if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(query, selectedStreetValue)) {
                // Проверяем, не является ли это просто обрезкой пробелов или незначительным изменением
                const trimmedQuery = normalizeAddressValue(query);
                const trimmedSelected = normalizeAddressValue(selectedStreetValue);
                if (trimmedQuery !== trimmedSelected && !trimmedSelected.startsWith(trimmedQuery)) {
                    // Значительное изменение - сбрасываем флаг
                    isPlaceSelected = false;
                    selectedStreetValue = '';
                }
            }

            // Показываем индикатор загрузки
            if (!customDropdown) createCustomDropdown();
            customDropdown.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280; font-size: 14px;">Завантаження...</div>';
            showCustomDropdown();

            inputTimeout = setTimeout(() => {
                const currentValue = streetInput.value.trim();

                if (currentValue !== currentQuery) {
                    return;
                }

                if (isProcessing) return;
                isProcessing = true;

                const autocompleteOptions = {
                    input: currentValue,
                    componentRestrictions: { country: 'ua' },
                };

                if (kyivOnly) {
                    autocompleteOptions.bounds = kyivBounds;
                    autocompleteOptions.strictBounds = true;
                }

                autocompleteService.getPlacePredictions(
                    autocompleteOptions,
                    (predictions, status) => {
                        isProcessing = false;

                        const latestValue = streetInput.value.trim();
                        if (latestValue !== currentQuery) {
                            return;
                        }

                        if (status !== google.maps.places.PlacesServiceStatus.OK || !predictions || predictions.length === 0) {
                            if (customDropdown) {
                                customDropdown.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280; font-size: 14px;">Адреси не знайдено</div>';
                                showCustomDropdown();
                            }
                            return;
                        }

                        // Фильтруем по зонам доставки
                        filterPredictionsByDeliveryZone(predictions, (filtered) => {
                            const finalValue = streetInput.value.trim();
                            if (finalValue !== currentQuery) {
                                return;
                            }

                            if (!customDropdown) createCustomDropdown();

                            if (filtered.length === 0) {
                                customDropdown.innerHTML = '<div style="padding: 16px; text-align: center; color: #6b7280; font-size: 14px;">Адреси не знайдено в зоні доставки</div>';
                                showCustomDropdown();
                                return;
                            }

                            customDropdown.innerHTML = '';

                            filtered.forEach((item) => {
                                const prediction = item.prediction;
                                const hint = buildAddressHint(prediction, item.place);
                                const mainText = escapeHtml(prediction.structured_formatting?.main_text || prediction.description || '');
                                const highlightedHint = escapeHtml(hint.highlighted);
                                const secondaryHint = escapeHtml(hint.secondary);
                                const element = document.createElement('div');
                                element.className = 'pac-item';
                                element.style.cssText = 'padding: 12px 16px; margin: 2px 8px; cursor: pointer; font-size: 15px; line-height: 20px; overflow: visible; white-space: normal; border-radius: 8px; transition: background-color 0.2s ease, box-shadow 0.2s ease; min-height: 44px; display: flex; align-items: flex-start;';

                                element.innerHTML = `
                                    <span class="pac-icon pac-icon-marker" style="width: 18px; height: 18px; margin-right: 12px; display: inline-block; vertical-align: middle; flex-shrink: 0; color: #6b7280;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                    </span>
                                    <span class="pac-item-query" style="color: #111827; flex: 1; min-width: 0; overflow-wrap: anywhere;">
                                        <span class="pac-matched" style="font-weight: 500; display: block; margin-bottom: 2px; white-space: normal;">${mainText}</span>
                                        ${highlightedHint ? `<span style="color: #ea580c; font-size: 13px; font-weight: 600; display: block; margin-bottom: 1px; white-space: normal;">${highlightedHint}</span>` : ''}
                                        <span class="pac-item-query" style="color: #6b7280; font-size: 13px; display: block; white-space: normal;">${secondaryHint}</span>
                                    </span>
                                `;

                                element.addEventListener('mouseenter', () => {
                                    element.style.backgroundColor = '#fef3e8';
                                    element.style.boxShadow = '0 2px 4px rgba(0,0,0,0.08)';
                                });
                                element.addEventListener('mouseleave', () => {
                                    element.style.backgroundColor = 'transparent';
                                    element.style.boxShadow = 'none';
                                });

                                element.addEventListener('mousedown', (e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    isClickingDropdown = true;
                                });

                                    element.addEventListener('click', (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        isClickingDropdown = false;

                                        const place = item.place;
                                        const prediction = item.prediction;

                                        // Добавляем place_id к объекту place, если его нет
                                        if (!place.place_id && prediction && prediction.place_id) {
                                            place.place_id = prediction.place_id;
                                        }

                                        const comps = place.address_components || [];
                                        let street = '';
                                        let streetNumber = '';
                                        let city = '';
                                        let administrativeArea = '';

                                        for (const c of comps) {
                                            if (c.types.includes('locality')) {
                                                city = c.long_name || '';
                                            }
                                            if (c.types.includes('administrative_area_level_1')) {
                                                administrativeArea = c.long_name || '';
                                            }
                                            if (c.types.includes('route')) {
                                                street = c.long_name;
                                            }
                                            if (c.types.includes('street_number')) {
                                                streetNumber = c.long_name;
                                            }
                                        }

                                        // Формируем строку города: если не Киев, добавляем область
                                        let cityValue = '';
                                        if (city) {
                                            // Проверяем, является ли это Киевом
                                            const isKyiv = city.toLowerCase().includes('київ') ||
                                                          city.toLowerCase().includes('киев') ||
                                                          city.toLowerCase().includes('kyiv');

                                            if (isKyiv) {
                                                cityValue = 'Київ';
                                            } else {
                                                // Для других городов добавляем область
                                                cityValue = city;
                                                if (administrativeArea) {
                                                    cityValue += ' ' + administrativeArea;
                                                }
                                            }
                                        }

                                        // Формируем полный адрес для поля улицы
                                        const predictionMain = prediction?.structured_formatting?.main_text || '';
                                        const predictionParts = uniqueAddressParts([
                                            street || predictionMain,
                                            hint.highlighted,
                                            hint.secondary,
                                        ]);
                                        let fullStreetValue = predictionParts.join(', ') || prediction?.description || '';
                                        if (cityValue && cityValue !== 'Київ') {
                                            // Если город не Киев, добавляем его к адресу улицы
                                            const fullStreetNorm = normalizeAddressValue(fullStreetValue);
                                            if (!fullStreetNorm.includes(normalizeAddressValue(cityValue))) {
                                                fullStreetValue = fullStreetValue + (fullStreetValue ? ', ' : '') + cityValue;
                                            }
                                        }

                                        // Устанавливаем флаги ПЕРЕД заполнением полей
                                        isSelectingFromGoogle = true;
                                        isPlaceSelected = true; // Устанавливаем сразу, чтобы blur не сработал

                                        // Заполняем поля
                                        if (fullStreetValue) {
                                            streetInput.value = fullStreetValue;
                                            selectedStreetValue = fullStreetValue;
                                            lastGoogleSelectionAt = Date.now();
                                        }

                                        if (streetNumber && houseInput) {
                                            houseInput.value = streetNumber;
                                            houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                                        }

                                        if (cityValue && cityInputSelector) {
                                            const cityInput = document.querySelector(cityInputSelector);
                                            if (cityInput) {
                                                cityInput.value = cityValue;
                                                cityInput.dispatchEvent(new Event('input', { bubbles: true }));
                                            }
                                        }

                                        // Не вызываем событие input для streetInput, чтобы не открывался dropdown повторно
                                        // streetInput.dispatchEvent(new Event('input', { bubbles: true }));

                                        // Скрываем dropdown перед вызовом callback
                                        hideCustomDropdown();

                                        if (onPlaceSelected && typeof onPlaceSelected === 'function') {
                                            onPlaceSelected({
                                                place,
                                                street: fullStreetValue, // Передаем полный адрес
                                                streetNumber,
                                                city: cityValue, // Передаем сформированный город
                                                streetInput,
                                                houseInput,
                                            });
                                        }

                                        // НЕ вызываем blur() - пусть пользователь сам переходит на другое поле
                                        // Это предотвратит преждевременное срабатывание валидации

                                        // Сбрасываем флаг выбора после большой задержки
                                        setTimeout(() => {
                                            isSelectingFromGoogle = false;
                                        }, 2000); // Увеличиваем задержку до 2000ms
                                });

                                customDropdown.appendChild(element);
                            });

                            showCustomDropdown();
                        });
                    }
                );
            }, 400);
        });

        // Скрываем dropdown при потере фокуса
        let blurTimeout = null;
        streetInput.addEventListener('blur', (e) => {
            // Если идет процесс выбора из Google Places, не скрываем dropdown сразу
            if (isSelectingFromGoogle) {
                return;
            }

            clearTimeout(blurTimeout);
            blurTimeout = setTimeout(() => {
                const relatedTarget = e.relatedTarget || document.activeElement;
                if (!isClickingDropdown && (!relatedTarget || !customDropdown || !customDropdown.contains(relatedTarget))) {
                    hideCustomDropdown();
                }
                isClickingDropdown = false;
            }, 200);
        });

        streetInput.addEventListener('focus', () => {
            clearTimeout(blurTimeout);
        });

        // Обрабатываем клик вне dropdown
        document.addEventListener('click', (e) => {
            if (customDropdown && customDropdown.style.display !== 'none') {
                if (!streetInput.contains(e.target) && !customDropdown.contains(e.target)) {
                    hideCustomDropdown();
                }
            }
        });

        // Валидация ручного ввода (та же логика, что и в стандартном режиме)
        streetInput.addEventListener('blur', function(e) {
            // Используем более длинную задержку, чтобы дать время всем событиям завершиться
            setTimeout(() => {
                // Если идет процесс выбора из Google Places, не валидируем
                if (isSelectingFromGoogle) {
                    return;
                }

                if (isClickingDropdown) {
                    return;
                }

                const currentValue = e.target.value;
                if (!currentValue || currentValue.trim() === '') {
                    return;
                }

                if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                    isPlaceSelected = true;
                    return;
                }

                if (Date.now() - lastGoogleSelectionAt < 1200) {
                    return;
                }

                // Если адрес был выбран из Google Places, не валидируем
                if (isPlaceSelected && selectedStreetValue) {
                    // Проверяем, что значение соответствует выбранному (с небольшой толерантностью)
                    if (currentValue && currentValue.trim() !== '' && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        e.target.value = selectedStreetValue;
                    }
                    return; // Всегда выходим, если адрес был выбран
                }

                // Если адрес не был выбран из Google Places, но есть значение - очищаем и показываем ошибку
                if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                    e.target.value = '';
                    showAddressErrorModal(addressText('manual_input_not_allowed'));
                }
            }, 1500); // Увеличиваем задержку до 1500ms, чтобы дать время событию выбора сработать и флагам установиться
        });

        streetInput.addEventListener('paste', function(e) {
            if (isPlaceSelected && selectedStreetValue) {
                setTimeout(() => {
                    if (!isAddressSelectionMatch(streetInput.value, selectedStreetValue)) {
                        streetInput.value = selectedStreetValue;
                        showAddressErrorModal(addressText('manual_input_not_allowed'));
                    }
                }, 0);
                return;
            }

            if (!isPlaceSelected) {
                e.preventDefault();
                showAddressErrorModal(addressText('manual_input_not_allowed'));     }
        });

        const form = streetInput.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const shippingMethod = (form.querySelector('input[name="shipping_method"]')?.value || '').trim();
                if (shippingMethod === 'pickup' || streetInput.disabled) {
                    return;
                }

                const currentValue = streetInput.value;

                if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                    isPlaceSelected = true;
                    return;
                }

                if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                    e.preventDefault();
                    e.stopPropagation();
                    showAddressErrorModal(addressText('manual_input_not_allowed'));
                    streetInput.focus();
                    return false;
                }

                if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                    e.preventDefault();
                    e.stopPropagation();
                    streetInput.value = selectedStreetValue;
                    showAddressErrorModal(addressText('manual_input_not_allowed'));
                    streetInput.focus();
                    return false;
                }
            }, true);
        }
    } catch (e) {
        // console.error('Ошибка инициализации автозаполнения с фильтрацией по зонам:', e);
        // Fallback: используем стандартное автозаполнение
        initStandardAutocomplete();
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
            // console.error('address-autocomplete: Google Maps API key is required. Set window.GOOGLE_MAPS_API_KEY or pass googleMapsKey option.');
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
    window.showAddressErrorModal = showAddressErrorModal;
}

// Экспортируем для использования в других модулях (ES6)
export default {
    init: initAddressAutocomplete,
};

export { initAddressAutocomplete };
