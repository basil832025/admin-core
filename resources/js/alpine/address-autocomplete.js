/**
 * Библиотека для автозаполнения адресов через Google Places API
 * Поддерживает ограничение поиска только по Киеву
 */

/**
 * Показывает модальное окно с сообщением об ошибке
 * @param {string} message - Текст сообщения
 */
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
                    <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
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

            // Сохраняем выбранное значение улицы из Google Places
            let selectedStreetValue = '';
            let isPlaceSelected = false;
            let isSelectingFromGoogle = false; // Флаг, что идет процесс выбора из Google Places

            autocomplete.addListener('place_changed', function () {
                // Устанавливаем флаг, что выбор происходит из Google Places
                isSelectingFromGoogle = true;
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
                        selectedStreetValue = '';
                        isPlaceSelected = false;
                        showAddressErrorModal('Доставка зараз працює тільки по Києву. Будь ласка, оберіть адресу в межах Києва.');
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
                    selectedStreetValue = street;
                    isPlaceSelected = true;
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
                
                // Если адрес был выбран из Google Places
                if (isPlaceSelected && selectedStreetValue) {
                    // Если пользователь начал редактировать выбранный адрес
                    if (currentValue !== selectedStreetValue) {
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
                    
                    // Если адрес был выбран из Google Places, но значение изменилось - восстанавливаем
                    if (isPlaceSelected && selectedStreetValue && currentValue !== selectedStreetValue) {
                        e.target.value = selectedStreetValue;
                        return;
                    }
                    
                    // Если адрес не был выбран из Google Places, но есть значение - очищаем
                    if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                        e.target.value = '';
                        showAddressErrorModal('Будь ласка, оберіть адресу зі списку Google. Ручний ввід адреси не дозволено.');
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
                        if (streetInput.value !== selectedStreetValue) {
                            streetInput.value = selectedStreetValue;
                            showAddressErrorModal('Будь ласка, оберіть адресу зі списку Google. Вставка адреси не дозволена.');
                        }
                    }, 0);
                } else {
                    // Если адрес не выбран, запрещаем вставку
                    e.preventDefault();
                    alert('Будь ласка, оберіть адресу зі списку Google. Вставка адреси не дозволена.');
                }
            });

            // Добавляем валидацию при отправке формы
            const form = streetInput.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const currentValue = streetInput.value;
                    
                    // Если адрес не был выбран из Google Places, но есть значение - блокируем отправку
                    if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                        e.preventDefault();
                        e.stopPropagation();
                        showAddressErrorModal('Будь ласка, оберіть адресу зі списку Google. Ручний ввід адреси не дозволено.');
                        streetInput.focus();
                        return false;
                    }
                    
                    // Если адрес был выбран, но значение изменилось - блокируем отправку
                    if (isPlaceSelected && selectedStreetValue && currentValue !== selectedStreetValue) {
                        e.preventDefault();
                        e.stopPropagation();
                        streetInput.value = selectedStreetValue;
                        showAddressErrorModal('Будь ласка, оберіть адресу зі списку Google. Ручний ввід адреси не дозволено.');
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
    window.showAddressErrorModal = showAddressErrorModal;
}

// Экспортируем для использования в других модулях (ES6)
export default {
    init: initAddressAutocomplete,
};

export { initAddressAutocomplete };
