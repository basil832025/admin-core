@php
    $isCallcenterOrderForm = request()->routeIs('filament.admin.resources.callcenter.orders.create')
        || request()->routeIs('filament.admin.resources.callcenter.orders.edit');
    $isCallcenterOrdersList = request()->routeIs('filament.admin.resources.callcenter.orders.index');
    $isLogisticsOrdersList = request()->routeIs('filament.admin.resources.logistics.orders.index');
    $isKitchenTicketsList = request()->routeIs('filament.admin.resources.kitchen.kitchen-tickets.index');
@endphp

@if($isCallcenterOrdersList || $isLogisticsOrdersList || $isKitchenTicketsList)
    <style>
        .fi-resource-list-records-page .fi-ta,
        .fi-resource-list-records-page .fi-ta *:not(svg):not(path) {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .fi-resource-list-records-page .fi-ta .fi-ta-header-cell-label,
        .fi-resource-list-records-page .fi-ta .fi-input,
        .fi-resource-list-records-page .fi-ta input,
        .fi-resource-list-records-page .fi-ta textarea,
        .fi-resource-list-records-page .fi-ta select,
        .fi-resource-list-records-page .fi-ta .fi-badge,
        .fi-resource-list-records-page .fi-ta .fi-dropdown-list-item-label,
        .fi-resource-list-records-page .fi-ta .fi-ta-text,
        .fi-resource-list-records-page .fi-ta .fi-ta-text-item,
        .fi-resource-list-records-page .fi-ta .fi-ta-text-item-label,
        .fi-resource-list-records-page .fi-ta .fi-ta-summary-row-heading,
        .fi-resource-list-records-page .fi-ta td,
        .fi-resource-list-records-page .fi-ta th {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }
    </style>
@endif

@if($isCallcenterOrderForm)
    <style>
        [x-cloak] {
            display: none !important;
        }

        .fi-resource-edit-record-page .fi-fo,
        .fi-resource-create-record-page .fi-fo {
            column-gap: 0.5rem !important;
            row-gap: 0.75rem !important;
        }

        .fi-resource-edit-record-page .fi-section-content-ctn.p-6,
        .fi-resource-create-record-page .fi-section-content-ctn.p-6,
        .fi-resource-edit-record-page .fi-section-content.p-6,
        .fi-resource-create-record-page .fi-section-content.p-6 {
            padding: 0.5rem !important;
        }

        .fi-resource-edit-record-page .fi-fo-label,
        .fi-resource-create-record-page .fi-fo-label,
        .fi-resource-edit-record-page .fi-fo-field-wrp-label,
        .fi-resource-create-record-page .fi-fo-field-wrp-label,
        .fi-resource-edit-record-page .fi-fo-field-wrp-label span,
        .fi-resource-create-record-page .fi-fo-field-wrp-label span,
        .fi-resource-edit-record-page label,
        .fi-resource-create-record-page label,
        .fi-resource-edit-record-page .fi-section-header-heading,
        .fi-resource-create-record-page .fi-section-header-heading,
        .fi-resource-edit-record-page .fi-tabs-item-label,
        .fi-resource-create-record-page .fi-tabs-item-label,
        .fi-resource-edit-record-page .fi-ta-header-cell-label,
        .fi-resource-create-record-page .fi-ta-header-cell-label,
        .callcenter-items-table .table-repeater-header-column,
        .callcenter-items-table .table-repeater-header-column * {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .fi-resource-edit-record-page .fi-input,
        .fi-resource-create-record-page .fi-input,
        .fi-resource-edit-record-page input,
        .fi-resource-create-record-page input,
        .fi-resource-edit-record-page .fi-select-input,
        .fi-resource-create-record-page .fi-select-input,
        .fi-resource-edit-record-page .choices__inner,
        .fi-resource-create-record-page .choices__inner,
        .fi-resource-edit-record-page .choices__item,
        .fi-resource-create-record-page .choices__item,
        .fi-resource-edit-record-page .ts-control,
        .fi-resource-create-record-page .ts-control,
        .fi-resource-edit-record-page .ts-control .item,
        .fi-resource-create-record-page .ts-control .item,
        .fi-resource-edit-record-page .flatpickr-input,
        .fi-resource-create-record-page .flatpickr-input {
            font-size: 12px !important;
            line-height: 1.25rem !important;
        }

        .callcenter-items-table .table-repeater-header-column,
        .callcenter-items-table .table-repeater-column {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .table-repeater-container thead .table-repeater-header-column {
            background: #e0f2fe !important;
        }

        .callcenter-items-table .table-repeater-container tbody tr {
            border-bottom: 1px solid #dbeafe;
        }

        .callcenter-items-table .table-repeater-container tbody tr + tr {
            box-shadow: inset 0 1px 0 #e0f2fe;
        }

        .callcenter-items-table .table-repeater-container table {
            border-collapse: separate !important;
            border-spacing: 4px 0 !important;
            font-size: 12px !important;
            line-height: 1.25rem;
        }

        .callcenter-items-table .table-repeater-container table th,
        .callcenter-items-table .table-repeater-container table td,
        .callcenter-items-table .callcenter-inline-display,
        .callcenter-items-table .callcenter-inline-input-display,
        .callcenter-items-table .callcenter-inline-input-price,
        .callcenter-items-table .callcenter-inline-input-qty,
        .callcenter-items-table .callcenter-inline-select .choices__inner,
        .callcenter-items-table .callcenter-inline-select .choices__item,
        .callcenter-items-table .callcenter-unit-text,
        .callcenter-items-table .callcenter-inline-item-total {
            font-size: 12px !important;
            line-height: 1.25rem !important;
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

        .callcenter-items-table .callcenter-inline-input-display.is-qty-display {
            text-align: center;
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

        .callcenter-kitchen-note-btn {
            width: 22px;
            height: 22px;
            border-radius: 999px;
            border: 1px solid #16a34a;
            color: #15803d;
            background: #f0fdf4;
            font-size: 14px;
            font-weight: 700;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .callcenter-kitchen-note-btn.is-active {
            background: #22c55e;
            color: #ffffff;
            border-color: #16a34a;
        }

        .callcenter-kitchen-note-popover {
            position: absolute;
            top: -4px;
            left: calc(100% + 8px);
            z-index: 80;
            width: 260px;
            background: #ffffff;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(2, 6, 23, 0.18);
            padding: 8px;
        }

        .callcenter-kitchen-note-textarea {
            width: 100%;
            min-height: 84px;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.35;
            resize: vertical;
            outline: none;
        }

        .callcenter-kitchen-note-textarea:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.2);
        }

        .callcenter-kitchen-note-actions {
            margin-top: 8px;
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }

        .callcenter-kitchen-note-save,
        .callcenter-kitchen-note-cancel {
            border-radius: 8px;
            font-size: 12px;
            line-height: 1;
            padding: 6px 10px;
            cursor: pointer;
        }

        .callcenter-kitchen-note-save {
            border: 1px solid #16a34a;
            background: #16a34a;
            color: #ffffff;
        }

        .callcenter-kitchen-note-cancel {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #334155;
        }

        .callcenter-items-table .callcenter-inline-select-display.has-note {
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
            line-height: 1.2;
            padding-top: 4px;
            padding-bottom: 4px;
        }

        .callcenter-items-table .callcenter-inline-select-display-note {
            display: block;
            margin-top: 4px;
            margin-left: 8px;
            margin-right: 4px;
            color: #15803d;
            font-size: 11px;
            line-height: 1.2;
            font-weight: 700;
            background: #dcfce7;
            border: 1px solid #86efac;
            border-radius: 6px;
            padding: 3px 6px;
            white-space: normal;
            word-break: break-word;
        }

        .callcenter-items-table .callcenter-unit-text {
            font-size: 10px !important;
            line-height: 1.25rem;
            background: #fef08a;
            border-radius: 6px;
            padding: 0 4px;
        }

        .callcenter-items-table .callcenter-inline-item-total {
            color: #0369a1 !important;
            font-size: 12px !important;
            line-height: 1.25rem !important;
            font-weight: 700 !important;
            font-variant-numeric: tabular-nums;
        }

        .callcenter-items-table .callcenter-inline-item-total,
        .callcenter-items-table .callcenter-inline-item-total * {
            color: #0369a1 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
        }

        .callcenter-items-table .callcenter-inline-input-price {
            min-height: 28px !important;
        }

        .callcenter-items-table .callcenter-inline-editable-wrapper .fi-input-wrp {
            display: none !important;
        }

        .callcenter-items-table .callcenter-inline-editable-wrapper.is-editing .fi-input-wrp {
            display: flex !important;
            align-items: center !important;
            min-height: 28px !important;
            padding-left: 2px !important;
            padding-right: 2px !important;
            box-shadow: none !important;
            border-color: #d1d5db !important;
            background: #ffffff !important;
        }

        .callcenter-items-table .callcenter-inline-price-wrapper.is-editing .fi-input-wrp-input {
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-price-wrapper.is-editing .callcenter-inline-input-price {
            width: 100% !important;
            min-width: 0 !important;
            text-align: right !important;
            font-variant-numeric: tabular-nums;
            line-height: 1.1 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .callcenter-items-table .callcenter-inline-input-qty {
            min-height: 28px !important;
            text-align: center !important;
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .callcenter-items-table .callcenter-inline-input-qty::-webkit-outer-spin-button,
        .callcenter-items-table .callcenter-inline-input-qty::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
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

        /* Hide clear/remove crosses in all selects on callcenter order form */
        .fi-resource-edit-record-page .choices__button,
        .fi-resource-create-record-page .choices__button,
        .fi-resource-edit-record-page .ts-wrapper .clear-button,
        .fi-resource-create-record-page .ts-wrapper .clear-button,
        .fi-resource-edit-record-page .ts-control .remove,
        .fi-resource-create-record-page .ts-control .remove {
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
            if (typeof window.textareaFormComponent !== 'function') {
                window.textareaFormComponent = function (config = {}) {
                    return {
                        state: config.state ?? '',
                        shouldAutosize: !!(config.shouldAutosize ?? false),
                        initialHeight: Number(config.initialHeight ?? 0),
                        init() {},
                        autosize() {},
                    };
                };
            }

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

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            window.callcenterSaveKitchenNote = function (el) {
                const row = el?.closest('tr')
                    || el?.closest('.table-repeater-row')
                    || el?.closest('[data-repeater-item]')
                    || el?.closest('.table-repeater-container')
                    || document;
                const select = row.querySelector('select[name$="[product_id]"]') || row.querySelector('select[name*="product_id"]');
                const idInput = row.querySelector('input[name$="[id]"]') || row.querySelector('input[name*="[id]"]');
                const orderItemId = Number(el?.dataset?.orderItemId || idInput?.value || 0);
                let input = row.querySelector('input[name$="[kitchen_note]"]')
                    || row.querySelector('input[name*="kitchen_note"]');

                if (!input && select?.name && typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                    const expectedName = select.name.replace('[product_id]', '[kitchen_note]');
                    input = document.querySelector(`input[name="${CSS.escape(expectedName)}"]`);
                }

                const popover = el?.closest('.callcenter-kitchen-note-popover');
                const textarea = popover ? popover.querySelector('.callcenter-kitchen-note-textarea') : null;
                const noteValue = String(textarea?.value || '').trim();

                if (!input) {
                    return { orderItemId, noteValue };
                }

                input.value = noteValue;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));

                if (select) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                return { orderItemId, noteValue };
            };

            window.callcenterHandleKitchenNoteSave = function (el, wire) {
                const payload = window.callcenterSaveKitchenNote(el) || {};
                const orderItemId = Number(payload.orderItemId || 0);
                const noteValue = String(payload.noteValue || '');

                if (!wire || orderItemId <= 0) {
                    return;
                }

                if (typeof wire.persistKitchenNoteInline === 'function') {
                    wire.persistKitchenNoteInline(orderItemId, noteValue);
                    return;
                }

                if (typeof wire.call === 'function') {
                    wire.call('persistKitchenNoteInline', orderItemId, noteValue);
                }
            };

            function resolveKitchenNoteInput(host, sourceSelect) {
                const row = host.closest('tr')
                    || host.closest('.table-repeater-row')
                    || host.closest('[data-repeater-item]')
                    || host.parentElement;
                let noteInput = row
                    ? (
                        row.querySelector('input[name$="[kitchen_note]"]')
                        || row.querySelector('input[name*="kitchen_note"]')
                    )
                    : null;

                if (!noteInput && sourceSelect?.name && typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                    const expectedName = sourceSelect.name.replace('[product_id]', '[kitchen_note]');
                    noteInput = document.querySelector(`input[name="${CSS.escape(expectedName)}"]`);
                }

                return noteInput;
            }

            function makeDisplayElement(target, className) {
                const host = target || null;
                if (!host) {
                    return null;
                }

                let display = host.querySelector(`.${className}`);

                if (!display) {
                    display = document.createElement('span');
                    display.className = `callcenter-inline-display ${className}`;
                    host.appendChild(display);
                }

                return display;
            }

            function setupInput(input) {
                if (!input || input.dataset.inlineInit === '1') {
                    return;
                }

                input.dataset.inlineInit = '1';

                const editableWrapper = input.closest('.callcenter-inline-editable-wrapper');
                const host = editableWrapper || input.parentElement;
                const display = makeDisplayElement(host, 'callcenter-inline-input-display');
                const editorContainer = editableWrapper?.querySelector('.fi-input-wrp') || input;
                const isPriceInput = input.classList.contains('callcenter-inline-input-price');
                const isQtyInput = input.classList.contains('callcenter-inline-input-qty');

                if (!display || !editorContainer) {
                    return;
                }

                if (isQtyInput) {
                    display.classList.add('is-qty-display');
                }

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
                    editorContainer.classList.remove('callcenter-inline-hidden');
                    if (editableWrapper) {
                        editableWrapper.classList.add('is-editing');
                    }
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
                    editorContainer.classList.add('callcenter-inline-hidden');
                    display.classList.remove('callcenter-inline-hidden');
                    if (editableWrapper) {
                        editableWrapper.classList.remove('is-editing');
                    }
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
                    const noteInput = resolveKitchenNoteInput(host, sourceSelect);
                    const note = (noteInput?.value || '').trim();

                    if (note !== '') {
                        const title = value || TEXT_EMPTY;

                        display.classList.add('has-note');
                        display.innerHTML = `
                            <span class="block truncate overflow-hidden text-ellipsis">${escapeHtml(title)}</span>
                            <span class="callcenter-inline-select-display-note">${escapeHtml(note)}</span>
                        `;
                    } else {
                        display.classList.remove('has-note');
                        display.textContent = value || TEXT_EMPTY;
                    }

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

                const bindNoteListeners = () => {
                    const noteInput = resolveKitchenNoteInput(host, sourceSelect);

                    if (!noteInput || noteInput.dataset.noteBound === '1') {
                        return;
                    }

                    noteInput.dataset.noteBound = '1';
                    noteInput.addEventListener('input', updateDisplay);
                    noteInput.addEventListener('change', updateDisplay);
                };

                bindNoteListeners();

                const syncObserver = new MutationObserver(() => {
                    bindNoteListeners();
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
                root.querySelectorAll('.callcenter-items-table .callcenter-inline-input-price, .callcenter-items-table .callcenter-inline-input-qty').forEach(setupInput);
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

            document.addEventListener('DOMContentLoaded', () => {
                initInlineEditors(document);
                initPhoneAutocomplete(document);
            });
        })();
    </script>
@endif
