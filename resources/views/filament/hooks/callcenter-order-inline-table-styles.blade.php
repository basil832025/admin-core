@php
    $isCallcenterOrderForm = request()->routeIs('filament.admin.resources.callcenter.orders.create')
        || request()->routeIs('filament.admin.resources.callcenter.orders.edit');
@endphp

@if($isCallcenterOrderForm)
    <style>
        .callcenter-items-table .table-repeater-header-column,
        .callcenter-items-table .table-repeater-column {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .table-repeater-container table {
            border-collapse: separate !important;
            border-spacing: 4px 0 !important;
        }

        .callcenter-inline-hidden {
            display: none !important;
        }

        .callcenter-items-table .callcenter-inline-display {
            display: block;
            min-height: 28px;
            line-height: 28px;
            padding: 0 6px;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: text;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .callcenter-items-table .callcenter-inline-display:hover {
            background: #f8fafc;
        }

        .callcenter-items-table .callcenter-inline-input-display {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-size: 0.875rem;
            line-height: 1.25rem;
            font-weight: 400;
            padding-right: 0;
            padding-left: 0;
        }

        .callcenter-items-table .callcenter-inline-input-display:hover {
            background: transparent;
        }

        .callcenter-items-table .callcenter-inline-display.is-empty {
            color: #94a3b8;
        }

        .callcenter-items-table .callcenter-inline-select-display,
        .callcenter-items-table .callcenter-inline-select .choices__inner,
        .callcenter-items-table .callcenter-inline-select .choices__item {
            font-size: 12px !important;
        }

        .callcenter-items-table .callcenter-unit-text {
            font-size: 12px !important;
            line-height: 1.25rem;
        }

        .callcenter-items-table .callcenter-inline-input-price {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-select .ts-control {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-select .choices {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-select .choices[data-type*="select-one"]::after {
            display: none !important;
        }

        .callcenter-client-select {
            position: relative;
        }

        .callcenter-client-side-results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 50;
            max-height: 280px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.12);
            display: none;
        }

        .callcenter-client-side-item {
            padding: 8px 10px;
            border-bottom: 1px solid #eef2f7;
            cursor: pointer;
            font-size: 13px;
        }

        .callcenter-client-side-item:last-child {
            border-bottom: none;
        }

        .callcenter-client-side-item:hover {
            background: #f8fafc;
        }

        .callcenter-client-side-item-phone {
            font-weight: 600;
            margin-right: 6px;
        }

        .callcenter-client-side-item-name {
            color: #64748b;
        }

    </style>

    <script>
        (function () {
            const TEXT_EMPTY = '-';

            function formatOneDecimal(value) {
                const normalized = String(value).replace(',', '.').trim();

                if (normalized === '') {
                    return '';
                }

                const number = Number(normalized);

                if (Number.isNaN(number)) {
                    return String(value);
                }

                return number.toFixed(1).replace('.', ',');
            }

            function makeDisplayElement(target, className) {
                let display = target.parentElement.querySelector(`.${className}`);

                if (!display) {
                    display = document.createElement('span');
                    display.className = `callcenter-inline-display ${className}`;
                    target.parentElement.appendChild(display);
                }

                return display;
            }

            function setupInput(input) {
                if (!input || input.dataset.inlineInit === '1') {
                    return;
                }

                input.dataset.inlineInit = '1';

                const display = makeDisplayElement(input, 'callcenter-inline-input-display');
                const isPriceInput = input.classList.contains('callcenter-inline-input-price');

                const updateDisplay = () => {
                    const value = (input.value || '').trim();
                    const formatted = input.classList.contains('callcenter-inline-input-price')
                        ? formatOneDecimal(value)
                        : value;

                    display.textContent = formatted || TEXT_EMPTY;
                    display.classList.toggle('is-empty', formatted === '');
                };

                const updateDisplayDeferred = () => {
                    updateDisplay();
                    setTimeout(updateDisplay, 60);
                    setTimeout(updateDisplay, 180);
                    setTimeout(updateDisplay, 450);
                };

                const openEditor = () => {
                    if (isPriceInput) {
                        const formatted = formatOneDecimal(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    display.classList.add('callcenter-inline-hidden');
                    input.classList.remove('callcenter-inline-hidden');
                    input.focus();
                    input.select();
                };

                const closeEditor = () => {
                    if (isPriceInput) {
                        const formatted = formatOneDecimal(input.value || '');
                        if (formatted !== '') {
                            input.value = formatted;
                        }
                    }

                    updateDisplay();
                    input.classList.add('callcenter-inline-hidden');
                    display.classList.remove('callcenter-inline-hidden');
                };

                display.addEventListener('click', openEditor);

                input.addEventListener('input', updateDisplay);
                input.addEventListener('change', updateDisplay);
                input.addEventListener('blur', closeEditor);
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        input.blur();
                    }

                    if (event.key === 'Escape') {
                        input.blur();
                    }
                });

                const syncObserver = new MutationObserver(() => {
                    updateDisplay();
                });

                syncObserver.observe(input, {
                    attributes: true,
                    attributeFilter: ['value'],
                });

                closeEditor();
                updateDisplayDeferred();
            }

            function getSelectText(sourceSelect, editorContainer) {
                if (sourceSelect && sourceSelect.selectedOptions && sourceSelect.selectedOptions.length > 0) {
                    const text = (sourceSelect.selectedOptions[0].textContent || '').trim();
                    if (text !== '') {
                        return text;
                    }
                }

                if (editorContainer) {
                    const item = editorContainer.querySelector('.choices__item.choices__item--selectable');
                    if (item) {
                        return (item.textContent || '').trim();
                    }
                }

                return '';
            }

            function setupSelect(host) {
                if (!host || host.dataset.inlineInit === '1') {
                    return;
                }

                const sourceSelect = host.querySelector('select');
                const editorContainer = host.querySelector('div[wire\\:ignore]') || sourceSelect;

                if (!sourceSelect || !editorContainer) {
                    return;
                }

                host.dataset.inlineInit = '1';

                const display = makeDisplayElement(host, 'callcenter-inline-select-display');

                const updateDisplay = () => {
                    const value = getSelectText(sourceSelect, editorContainer);
                    display.textContent = value || TEXT_EMPTY;
                    display.classList.toggle('is-empty', value === '');
                };

                const updateDisplayDeferred = () => {
                    updateDisplay();
                    setTimeout(updateDisplay, 80);
                    setTimeout(updateDisplay, 250);
                    setTimeout(updateDisplay, 700);
                };

                const openEditor = () => {
                    display.classList.add('callcenter-inline-hidden');
                    editorContainer.classList.remove('callcenter-inline-hidden');

                    const input = editorContainer.querySelector('input');
                    if (input) {
                        input.focus();
                    }
                };

                const closeEditor = () => {
                    updateDisplay();
                    editorContainer.classList.add('callcenter-inline-hidden');
                    display.classList.remove('callcenter-inline-hidden');
                };

                display.addEventListener('click', openEditor);

                sourceSelect.addEventListener('change', () => {
                    updateDisplay();
                    closeEditor();
                });

                sourceSelect.addEventListener('input', updateDisplay);

                const syncObserver = new MutationObserver(() => {
                    updateDisplay();
                });

                syncObserver.observe(editorContainer, {
                    childList: true,
                    subtree: true,
                    characterData: true,
                });

                editorContainer.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeEditor();
                    }
                });

                document.addEventListener('mousedown', (event) => {
                    if (display.classList.contains('callcenter-inline-hidden') && !host.contains(event.target)) {
                        closeEditor();
                    }
                });

                closeEditor();
                updateDisplayDeferred();
            }

            function initInlineEditors(root = document) {
                root.querySelectorAll('.callcenter-items-table .callcenter-inline-input-price').forEach(setupInput);
                root.querySelectorAll('.callcenter-items-table .callcenter-inline-select').forEach(setupSelect);
            }

            function setupPhoneAutocomplete(input) {
                if (!input || input.dataset.ccPhoneInit === '1') {
                    return;
                }

                input.dataset.ccPhoneInit = '1';

                let debounceTimer = null;
                let requestSeq = 0;
                let lastMirroredDigits = '';

                const clientHost = document.querySelector('.callcenter-client-select');
                if (!clientHost) {
                    return;
                }

                let sidePanel = clientHost.querySelector('.callcenter-client-side-results');

                if (!sidePanel) {
                    sidePanel = document.createElement('div');
                    sidePanel.className = 'callcenter-client-side-results';
                    clientHost.appendChild(sidePanel);
                }

                const closePanel = () => {
                    sidePanel.style.display = 'none';
                    sidePanel.innerHTML = '';
                };

                const closeClientDropdown = () => {
                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const choicesEl = clientHost.querySelector('.choices');
                    const dropdownEl = clientHost.querySelector('.choices__list--dropdown');

                    if (alpineData?.select && typeof alpineData.select.hideDropdown === 'function') {
                        alpineData.select.hideDropdown();
                    }

                    choicesEl?.classList.remove('is-open');
                    dropdownEl?.classList.remove('is-active');
                };

                const selectClient = (item) => {
                    if (!item || !item.id) {
                        return;
                    }

                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const stateSelect = wireIgnore?.querySelector('select');

                    input.value = String(item.phone || '');
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));

                    if (alpineData?.select) {
                        alpineData.select.setChoiceByValue(String(item.id));
                    }

                    if (stateSelect) {
                        stateSelect.value = String(item.id);
                        stateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    closePanel();
                };

                const renderPanel = (items) => {
                    sidePanel.innerHTML = '';

                    if (!Array.isArray(items) || items.length === 0) {
                        closePanel();
                        return;
                    }

                    for (const item of items) {
                        const row = document.createElement('div');
                        row.className = 'callcenter-client-side-item';

                        const phone = document.createElement('span');
                        phone.className = 'callcenter-client-side-item-phone';
                        phone.textContent = String(item.phone || '');

                        const name = document.createElement('span');
                        name.className = 'callcenter-client-side-item-name';
                        name.textContent = String(item.name || '');

                        row.appendChild(phone);
                        row.appendChild(name);
                        row.addEventListener('mousedown', (event) => event.preventDefault());
                        row.addEventListener('click', () => selectClient(item));

                        sidePanel.appendChild(row);
                    }

                    sidePanel.style.display = 'block';
                };

                const mirrorSearchToClientSelect = (digits) => {
                    const wireIgnore = clientHost.querySelector('div[wire\\:ignore]');
                    const alpineData = wireIgnore?._x_dataStack?.[0];
                    const stateSelect = wireIgnore?.querySelector('select');

                    if (!alpineData?.select) {
                        return;
                    }

                    if (digits === lastMirroredDigits) {
                        return;
                    }

                    lastMirroredDigits = digits;

                    if (typeof alpineData.refreshChoices === 'function') {
                        alpineData.refreshChoices({
                            search: digits,
                            withInitialOptions: false,
                        });
                    }

                    const clientSearchInput = alpineData.select.input?.element;
                    if (clientSearchInput) {
                        clientSearchInput.value = digits;
                        clientSearchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    if (stateSelect) {
                        stateSelect.dispatchEvent(new CustomEvent('search', {
                            detail: { value: digits },
                            bubbles: true,
                        }));
                    }

                    window.setTimeout(() => {
                        const choicesEl = clientHost.querySelector('.choices');
                        const dropdownEl = clientHost.querySelector('.choices__list--dropdown');

                        choicesEl?.classList.add('is-open');
                        dropdownEl?.classList.add('is-active');
                    }, 50);
                };

                const syncToClientSelect = () => {
                    const digits = String(input.value || '').replace(/\D+/g, '');

                    if (digits.length < 3) {
                        closePanel();
                        lastMirroredDigits = '';
                        closeClientDropdown();

                        return;
                    }

                    mirrorSearchToClientSelect(digits);

                    const seq = ++requestSeq;

                    fetch(`/admin/callcenter/clients/phone-suggestions?q=${encodeURIComponent(digits)}`, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    })
                        .then((response) => (response.ok ? response.json() : []))
                        .then((items) => {
                            if (seq !== requestSeq) {
                                return;
                            }

                            renderPanel(items);
                        })
                        .catch(() => {
                            closePanel();
                        });
                };

                const scheduleSync = () => {
                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(syncToClientSelect, 180);
                };

                input.addEventListener('input', scheduleSync);
                input.addEventListener('focus', scheduleSync);
                input.addEventListener('blur', () => {
                    window.setTimeout(closePanel, 150);
                    window.setTimeout(closeClientDropdown, 150);
                });
            }

            function initPhoneAutocomplete(root = document) {
                root.querySelectorAll('.callcenter-phone-input').forEach(setupPhoneAutocomplete);
            }

            function applyHistoryAddressToDom(payload) {
                if (!payload || typeof payload !== 'object') {
                    console.info('History apply: invalid payload', payload);
                    return;
                }

                const address = payload.address || {};
                const selectedAddressId = String(payload.selectedAddressId ?? '-1');

                const findField = (variants) => {
                    for (const variant of variants) {
                        const byName = document.querySelector(`[name="${variant}"]`);
                        if (byName) return byName;

                        const byContains = document.querySelector(`[name*="${variant}"]`);
                        if (byContains) return byContains;
                    }

                    return null;
                };

                const setFieldValue = (nameVariants, value) => {
                    const el = findField(Array.isArray(nameVariants) ? nameVariants : [nameVariants]);
                    if (!el) {
                        console.info('History apply: field NOT found', nameVariants);
                        return;
                    }

                    console.info('History apply: set field', {
                        name: el.getAttribute('name'),
                        id: el.getAttribute('id'),
                        value,
                    });

                    el.value = value ?? '';
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                };

                console.info('History apply: start', {
                    selectedAddressId,
                    address,
                    shippingPrice: payload.shippingPrice,
                });

                const findLivewireComponent = () => {
                    if (payload.componentId && window.Livewire && typeof window.Livewire.find === 'function') {
                        const byPayload = window.Livewire.find(payload.componentId);
                        if (byPayload) {
                            return byPayload;
                        }
                    }

                    const host = document.querySelector('.callcenter-phone-input')?.closest('[wire\\:id]')
                        || document.querySelector('[wire\\:id]');

                    if (!host) {
                        return null;
                    }

                    const id = host.getAttribute('wire:id');
                    if (!id || !window.Livewire || typeof window.Livewire.find !== 'function') {
                        return null;
                    }

                    return window.Livewire.find(id);
                };

                const livewire = findLivewireComponent();

                if (livewire && typeof livewire.set === 'function') {
                    console.info('History apply: using Livewire.set');

                    livewire.set('data.self_pickup', false);
                    livewire.set('data.selected_address_id', selectedAddressId);
                    livewire.set('data.address.street', address.street ?? '');
                    livewire.set('data.address.street_place_id', address.street_place_id ?? address.street ?? '');
                    livewire.set('data.address.house', address.house ?? '');
                    livewire.set('data.address.apartment', address.apartment ?? '');
                    livewire.set('data.address.entrance', address.entrance ?? '');
                    livewire.set('data.address.intercom', address.intercom ?? '');
                    livewire.set('data.address.floor', address.floor ?? '');
                    livewire.set('data.address.city', address.city ?? 'Київ');
                    livewire.set('data.address.note', address.note ?? '');
                    livewire.set('data.address.latitude', address.latitude ?? null);
                    livewire.set('data.address.longitude', address.longitude ?? null);
                    livewire.set('data.address.formatted_address', address.formatted_address ?? null);
                    livewire.set('data.address.type', address.type ?? null);
                    livewire.set('data.address.is_private_house', !!address.is_private_house);

                    if (payload.shippingPrice !== undefined) {
                        livewire.set('data.shipping_price', payload.shippingPrice);
                    }

                    // Триггерим перерисовку/пересчет зависимых полей.
                    livewire.set('data.delivery_coords_trigger', 'history_front_' + Date.now());
                } else {
                    console.info('History apply: Livewire component not found, fallback DOM only');
                }

                const allFormFields = Array.from(document.querySelectorAll('input[name], select[name], textarea[name]'))
                    .map((el) => ({
                        tag: el.tagName,
                        name: el.getAttribute('name'),
                        id: el.getAttribute('id'),
                    }));
                console.info('History apply: visible form fields', allFormFields);

                setFieldValue(['selected_address_id', 'data.selected_address_id'], selectedAddressId);
                setFieldValue(['address.street', 'data.address.street', '[address][street]'], address.street ?? '');
                setFieldValue(['address.street_place_id', 'data.address.street_place_id', '[address][street_place_id]'], address.street_place_id ?? address.street ?? '');
                setFieldValue(['address.house', 'data.address.house', '[address][house]'], address.house ?? '');
                setFieldValue(['address.apartment', 'data.address.apartment', '[address][apartment]'], address.apartment ?? '');
                setFieldValue(['address.entrance', 'data.address.entrance', '[address][entrance]'], address.entrance ?? '');
                setFieldValue(['address.intercom', 'data.address.intercom', '[address][intercom]'], address.intercom ?? '');
                setFieldValue(['address.floor', 'data.address.floor', '[address][floor]'], address.floor ?? '');
                setFieldValue(['address.city', 'data.address.city', '[address][city]'], address.city ?? 'Київ');
                setFieldValue(['address.note', 'data.address.note', '[address][note]'], address.note ?? '');
                setFieldValue(['address.latitude', 'data.address.latitude', '[address][latitude]'], address.latitude ?? '');
                setFieldValue(['address.longitude', 'data.address.longitude', '[address][longitude]'], address.longitude ?? '');
                setFieldValue(['address.formatted_address', 'data.address.formatted_address', '[address][formatted_address]'], address.formatted_address ?? '');
                setFieldValue(['address.type', 'data.address.type', '[address][type]'], address.type ?? '');

                const privateHouseToggle = findField([
                    'address.is_private_house',
                    'data.address.is_private_house',
                    '[address][is_private_house]',
                ]);
                if (privateHouseToggle) {
                    privateHouseToggle.checked = !!address.is_private_house;
                    privateHouseToggle.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const shipping = findField(['shipping_price', 'data.shipping_price']);
                if (shipping && payload.shippingPrice !== undefined) {
                    console.info('History apply: set shipping', {
                        name: shipping.getAttribute('name'),
                        id: shipping.getAttribute('id'),
                        value: payload.shippingPrice,
                    });
                    shipping.value = payload.shippingPrice;
                    shipping.dispatchEvent(new Event('input', { bubbles: true }));
                    shipping.dispatchEvent(new Event('change', { bubbles: true }));
                }

                console.info('History apply: done');
            }

            const onHistoryAddressApplied = (event) => {
                console.info('History apply: event received', event?.detail);
                applyHistoryAddressToDom(event?.detail || {});
            };

            window.addEventListener('callcenter-history-address-applied', onHistoryAddressApplied);
            document.addEventListener('callcenter-history-address-applied', onHistoryAddressApplied);

            document.addEventListener('livewire:init', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);

                const observer = new MutationObserver(() => {
                    initInlineEditors(document);
                    initPhoneAutocomplete(document);
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            });

            document.addEventListener('filament:form-mounted', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);
            });
        })();
    </script>
@endif
