/* =========================================================
 * checkout.js — Checkout page logic
 * Alpine components + totals + promos + autosave + delivery
 * ======================================================= */

document.addEventListener('alpine:init', () => {
    Alpine.data('deliveryBlock', deliveryBlock);

    // promoComponent у тебя объявлен как window.promoComponent
    Alpine.data('promoComponent', window.promoComponent);

    Alpine.data('tooltip', tooltip);
    // Alpine.data('couponComponent', window.couponComponent);
});




/* =========================================================
 * Helpers
 * ======================================================= */


const Money = {
    /**
     * Parses money from DOM text like "1 234,50 грн" => 1234.5
     */
    parse(text) {
        text = (text || '').toString().replace(/[^\d,.\-]/g, '').replace(/\s+/g, '');
        // prefer dot as decimal separator
        const lastComma = text.lastIndexOf(',');
        const lastDot = text.lastIndexOf('.');
        const decPos = Math.max(lastComma, lastDot);

        if (decPos !== -1) {
            const intPart = text.slice(0, decPos).replace(/[.,]/g, '');
            const fracPart = text.slice(decPos + 1).replace(/[^\d]/g, '');
            text = intPart + '.' + fracPart;
        } else {
            text = text.replace(/[^\d\-]/g, '');
        }

        const n = parseFloat(text);
        return isNaN(n) ? 0 : n;
    },

    /**
     * Formats number into UA format "1 234,50"
     */
    format(n) {
        n = Number(n || 0);
        try {
            return new Intl.NumberFormat('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
        } catch (e) {
            return (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
        }
    },
};

function debounce(fn, wait) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

window.checkoutPayparts = {
    lastAmount: null,

    scheduleUpdate: debounce(function (amount) {
        const form = document.querySelector('[data-checkout-form]');
        const payBlock = document.getElementById('blk-pay');
        const url = form?.dataset?.paypartsOptionsUrl;

        if (!form || !payBlock || !url) return;

        const roundedAmount = Math.round(Number(amount || 0) * 100) / 100;
        if (window.checkoutPayparts.lastAmount === roundedAmount) return;
        window.checkoutPayparts.lastAmount = roundedAmount;

        const activeFinancialPhone = form.querySelector('[name="payparts_financial_phone"]:not(:disabled)');
        const anyFinancialPhone = form.querySelector('[name="payparts_financial_phone"]');
        const activePlan = form.querySelector('[name="payparts_plan_key"]:not(:disabled)');
        const anyPlan = form.querySelector('[name="payparts_plan_key"]');
        const activeBank = form.querySelector('[name="payparts_bank_id"]:checked')
            || form.querySelector('[name="payparts_bank_id"]');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                amount: roundedAmount,
                payment_method: form.querySelector('[name="payment_method"]:checked')?.value || '',
                payparts_bank_id: activeBank?.value || '',
                payparts_plan_key: activePlan?.value || anyPlan?.value || '',
                payparts_financial_phone: activeFinancialPhone?.value || anyFinancialPhone?.value || '',
            }),
        })
            .then((response) => response.ok ? response.json() : null)
            .then((data) => {
                if (!data?.ok || typeof data.html !== 'string') return;
                payBlock.innerHTML = data.html;
                if (window.Alpine && typeof window.Alpine.initTree === 'function') {
                    window.Alpine.initTree(payBlock);
                }
            })
            .catch(() => {});
    }, 250),
};

function getShippingMethodValue(root = document) {
    const checked = root.querySelector('input[name="shipping_method"]:checked');
    if (checked && checked.value) return String(checked.value).trim();

    const direct = root.querySelector('[name="shipping_method"]');
    if (direct && direct.value) return String(direct.value).trim();

    return 'delivery';
}

function getSelectedAddressRadio(root = document) {
    const checked = root.querySelector('input[name="selected_address_id"]:checked');
    if (checked) return checked;

    const selectedId = String(root.querySelector('#selected_address_id')?.value || '').trim();
    if (!selectedId) return null;

    const esc = (window.CSS && typeof window.CSS.escape === 'function')
        ? window.CSS.escape(selectedId)
        : selectedId.replace(/(["\\\]])/g, '\\$1');
    const byValue = root.querySelector(`input[name="selected_address_id"][value="${esc}"]`);
    return byValue || null;
}


/* =========================================================
 * Debug helpers (enable via: localStorage.checkoutDebug="1")
 * ======================================================= */
const CHECKOUT_DEBUG = (localStorage.getItem('checkoutDebug') === '1');

function dlog(...args) {
    if (CHECKOUT_DEBUG) console.log('[checkout]', ...args);
}
function dwarn(...args) {
    if (CHECKOUT_DEBUG) console.warn('[checkout]', ...args);
}
function derr(...args) {
    if (CHECKOUT_DEBUG) console.error('[checkout]', ...args);
}

/* =========================================================
 * Alpine: tooltip
 * ======================================================= */

function tooltip(text = '') {
    return {
        open: false,
        text,
        toggle() { this.open = !this.open; },
        show() { this.open = true; },
        hide() { this.open = false; },
    };
}

/* =========================================================
 * Alpine: deliveryBlock (date + time)
 * IMPORTANT: keeps flatpickr behavior and formatting
 * ======================================================= */

function deliveryBlock() {
    return {
        mode: 'asap',
        fpDate: null,
        scheduleV2: null,
        availableDates: [],
        asapEnabled: true,

        allTimeIntervals: [],
        availableTimeIntervals: [],

        selectedTime: '',
        savedTime: '',

        // minutes needed for preparation
        leadMinutes: 60,

        // Debounce timer для checkPromoConditions (используем глобальный для всех вызовов)

        init() {
            this.scheduleV2 = window.CHECKOUT_CONFIG?.scheduleV2 || null;
            // flatpickr locale (RU)
            const ruLocale = {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'],
                    longhand:  ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'],
                },
                months: {
                    shorthand: ['Янв','Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт','Ноя','Дек'],
                    longhand:  ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
                },
            };

            const pad = n => String(n).padStart(2, '0');
            const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const todayStr = () => ymd(new Date());
            const tomorrowStr = () => { const d = new Date(); d.setDate(d.getDate() + 1); return ymd(d); };

            const autoPickTimeIfNeeded = () => {
                if (this.mode !== 'fixed') return;

                // if user already selected something — keep
                if (this.selectedTime) return;

                // try restore saved time
                if (this.savedTime && this.availableTimeIntervals.includes(this.savedTime)) {
                    this.selectedTime = this.savedTime;
                    return;
                }

                // else pick first available
                if (this.availableTimeIntervals.length) {
                    this.selectedTime = this.availableTimeIntervals[0];
                }
            };

            const moveToTomorrowIfNoIntervalsToday = () => {
                if (!this.fpDate) return;

                const sel = this.fpDate.selectedDates?.[0];
                if (!sel) return;

                const today = new Date();
                today.setHours(0,0,0,0);

                const sel0 = new Date(sel);
                sel0.setHours(0,0,0,0);

                // If selected today but no intervals — move to tomorrow
                if (sel0.getTime() === today.getTime() && (!this.availableTimeIntervals || this.availableTimeIntervals.length === 0)) {
                    this.fpDate.setDate(tomorrowStr(), true);
                }
            };

            // init flatpickr
            this.fpDate = flatpickr(this.$refs.date, {
                minDate: todayStr(),
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y',
                altInputClass: 'tp-input pr-10',
                locale: ruLocale,
                disableMobile: true,
                clickOpens: false,

                onReady: (_, __, inst) => {
                    inst.altInput.placeholder = this.$refs.date.placeholder || 'Дата*';
                },

                onChange: (sel) => {
                    if (!sel.length) return;

                    this.updateAvailableTimeIntervals();

                    this.$nextTick(() => {
                        moveToTomorrowIfNoIntervalsToday();
                        autoPickTimeIfNeeded();
                        // Проверяем условия акций при изменении даты (debounce уже внутри метода)
                        // Небольшая задержка, чтобы дать время обновиться selectedTime если нужно
                        setTimeout(() => {
                            if (typeof this.checkPromoConditions === 'function') {
                                this.checkPromoConditions();
                            }
                        }, 50);
                    });
                },
            });

            // initial state
            this.updateFieldsState();

            // initial intervals
            this.$nextTick(() => {
                this.updateAvailableTimeIntervals();
                this.$nextTick(() => {
                    moveToTomorrowIfNoIntervalsToday();
                    autoPickTimeIfNeeded();
                });
            });

            // watchers
            this.$watch('mode', (newVal, oldVal) => {
                // Пропускаем если значение не изменилось или это начальная инициализация
                if (newVal === oldVal) return;
                
                this.updateFieldsState();

                if (this.mode === 'fixed') {
                    this.$nextTick(() => {
                        this.updateAvailableTimeIntervals();
                        this.$nextTick(() => {
                            moveToTomorrowIfNoIntervalsToday();
                            autoPickTimeIfNeeded();
                        });
                    });
                } else if (this.mode === 'asap') {
                    // При переключении на "asap" очищаем выбранное время
                    this.selectedTime = '';
                }

                // persist (autosave trigger)
                this.saveFormData();
                // Проверяем условия акций при изменении режима доставки (debounce уже внутри метода)
                // Небольшая задержка, чтобы дать время обновиться полям
                setTimeout(() => {
                    if (typeof this.checkPromoConditions === 'function') {
                        this.checkPromoConditions();
                    }
                }, 100);
            });

            this.$watch('selectedTime', (newVal, oldVal) => {
                // Пропускаем если значение не изменилось или это начальная инициализация
                if (newVal === oldVal) {
                    return;
                }
                
                if (this.mode === 'fixed') {
                    this.saveFormData();
                    // Проверяем условия акций при изменении времени (debounce уже внутри метода)
                    // Небольшая задержка, чтобы избежать конфликтов с другими вызовами
                    setTimeout(() => {
                        if (typeof this.checkPromoConditions === 'function') {
                            this.checkPromoConditions();
                        }
                    }, 50);
                }
            });

            this.$watch('availableTimeIntervals', () => {
                if (this.mode === 'fixed') this.$nextTick(() => autoPickTimeIfNeeded());
            });

            document.addEventListener('change', (e) => {
                if (e.target?.matches('[name="shipping_method"]')) {
                    this.applyScheduleV2Availability();
                    this.updateAvailableTimeIntervals();
                }
            });

            this.applyScheduleV2Availability();
        },

        getCurrentShippingMethod() {
            const form = document.querySelector('[data-checkout-form]');
            const checked = form?.querySelector('input[name="shipping_method"]:checked');
            if (checked?.value) return String(checked.value).trim();

            return form?.querySelector('[name="shipping_method"]')?.value || 'delivery';
        },

        getScheduleV2MethodPayload() {
            if (!this.scheduleV2 || !this.scheduleV2.enabled) return null;
            return this.scheduleV2.methods?.[this.getCurrentShippingMethod()] || null;
        },

        applyScheduleV2Availability() {
            const payload = this.getScheduleV2MethodPayload();
            if (!payload) return;

            this.availableDates = Array.isArray(payload.available_dates) ? payload.available_dates : [];
            this.asapEnabled = !!payload.asap_available;

            const asapRadio = this.$el?.querySelector('input[type="radio"][value="asap"]');
            if (asapRadio) {
                asapRadio.disabled = !this.asapEnabled;
            }

            if (!this.asapEnabled && this.mode === 'asap') {
                this.mode = 'fixed';
            }

            if (this.fpDate) {
                this.fpDate.set('enable', this.availableDates);
                if (this.availableDates.length === 0) {
                    this.fpDate.clear();
                    this.availableTimeIntervals = [];
                    this.selectedTime = '';
                    return;
                }

                const currentDate = this.$refs.date?.value || '';
                if (!currentDate || !this.availableDates.includes(currentDate)) {
                    this.fpDate.setDate(payload.next_available_date || this.availableDates[0], true);
                }
            }
        },

        /**
         * Filters intervals for "today" based on leadMinutes
         */
        updateAvailableTimeIntervals() {
            const methodPayload = this.getScheduleV2MethodPayload();
            if (methodPayload) {
                if (!this.$refs.date || !this.fpDate) {
                    this.availableTimeIntervals = [];
                    return;
                }

                const selectedDate = this.fpDate.selectedDates?.[0];
                if (!selectedDate) {
                    this.availableTimeIntervals = [];
                    return;
                }

                const y = selectedDate.getFullYear();
                const m = String(selectedDate.getMonth() + 1).padStart(2, '0');
                const d = String(selectedDate.getDate()).padStart(2, '0');
                const dateKey = `${y}-${m}-${d}`;

                this.availableTimeIntervals = methodPayload.slots_by_date?.[dateKey] || [];
                if (this.selectedTime && !this.availableTimeIntervals.includes(this.selectedTime)) {
                    this.selectedTime = '';
                }
                return;
            }

            if (!this.$refs.date || !this.fpDate) {
                this.availableTimeIntervals = this.allTimeIntervals || [];
                return;
            }

            const selectedDate = this.fpDate.selectedDates[0];
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // keep currently selected time
            const currentSelected = this.selectedTime;

            // not today => all intervals
            if (!selectedDate || selectedDate.getTime() !== today.getTime()) {
                this.availableTimeIntervals = this.allTimeIntervals || [];
                if (currentSelected && this.availableTimeIntervals.includes(currentSelected)) {
                    this.selectedTime = currentSelected;
                }
                return;
            }

            // today => remove past intervals
            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const minMinutes = nowMinutes + (this.leadMinutes || 0);

            this.availableTimeIntervals = (this.allTimeIntervals || []).filter(interval => {
                const match = interval.match(/^(\d{2}):(\d{2})-/);
                if (!match) return true;
                const intervalStartMinutes = parseInt(match[1], 10) * 60 + parseInt(match[2], 10);
                return intervalStartMinutes >= minMinutes;
            });

            // keep selected if still valid
            if (currentSelected && this.availableTimeIntervals.includes(currentSelected)) {
                this.selectedTime = currentSelected;
            }
        },

        checkPromoConditions() {
            // Используем глобальный таймер для всех вызовов
            if (window.globalCheckPromoConditionsTimer) {
                clearTimeout(window.globalCheckPromoConditionsTimer);
            }

            // Устанавливаем новый таймер с задержкой 300ms
            window.globalCheckPromoConditionsTimer = setTimeout(() => {
                // Получаем данные формы
                const form = document.querySelector('[data-checkout-form]');
                if (!form) {
                    if (CHECKOUT_DEBUG) console.warn('checkPromoConditions: form not found');
                    return;
                }

                const formData = new FormData(form);
                const shippingMethod = formData.get('shipping_method') || 'delivery';
                const deliveryMode = this.mode || 'asap';
                
                // Получаем дату и время в зависимости от режима доставки
                let deliveryDate = null;
                let deliveryTime = null;
                
                if (deliveryMode === 'fixed') {
                    // Для "До визначеного часу" получаем дату и время
                    if (this.fpDate && this.fpDate.selectedDates && this.fpDate.selectedDates.length > 0) {
                        const selectedDate = this.fpDate.selectedDates[0];
                        const year = selectedDate.getFullYear();
                        const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                        const day = String(selectedDate.getDate()).padStart(2, '0');
                        deliveryDate = `${year}-${month}-${day}`;
                    } else {
                        deliveryDate = formData.get('delivery_date') || null;
                    }
                    deliveryTime = this.selectedTime || formData.get('delivery_time') || null;
                } else {
                    // Для "Якнайшвидше" дата и время должны быть null
                    deliveryDate = null;
                    deliveryTime = null;
                }

                // Получаем CSRF токен и URL из мета-тегов или data-атрибутов
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                                 document.querySelector('[data-csrf-token]')?.dataset.csrfToken || '';
                const checkUrl = document.querySelector('[data-check-promo-url]')?.dataset.checkPromoUrl || 
                               '/checkout/check-promo-conditions';

                // Формируем payload для запроса
                const payload = {
                    shipping_method: shippingMethod,
                    delivery_mode: deliveryMode,
                    delivery_date: deliveryDate,
                    delivery_time: deliveryTime,
                };
                
                // Отправляем запрос на проверку условий
                fetch(checkUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.promos && Array.isArray(data.promos)) {
                            // Небольшая задержка для обеспечения инициализации всех компонентов
                            setTimeout(() => {
                                const event = new CustomEvent('update-promo-status', {
                                    detail: data.promos,
                                    bubbles: true,
                                    cancelable: true
                                });
                                window.dispatchEvent(event);
                            }, 10);
                        }
                    })
                    .catch(() => {
                        // ошибка запроса просто игнорируется
                    })
                    .finally(() => {
                        window.globalCheckPromoConditionsTimer = null;
                    });
            }, 300);
        },

        saveFormData() {
            const form = document.querySelector('[data-checkout-form]');
            if (!form) return;
            form.dispatchEvent(new Event('change'));
        },

        /**
         * Enables/disables date + time fields depending on mode
         */
        updateFieldsState() {
            const fixed = this.mode === 'fixed';

            if (this.fpDate) {
                this.fpDate.set('clickOpens', fixed);
            }

            const altDate = this.fpDate?.altInput;
            if (altDate) {
                altDate.readOnly = !fixed;
                altDate.disabled = !fixed;

                altDate.classList.toggle('bg-[#F9FAFB]', !fixed);
                altDate.classList.toggle('text-[#9CA3AF]', !fixed);
                altDate.classList.toggle('cursor-not-allowed', !fixed);

                if (fixed) altDate.setAttribute('required', 'required');
                else altDate.removeAttribute('required');
            }

            const timeSelect = this.$refs.time;
            if (timeSelect) {
                timeSelect.disabled = !fixed;
                if (fixed) timeSelect.setAttribute('required', 'required');
                else timeSelect.removeAttribute('required');
            }

            // switching to asap => clear
            if (!fixed) {
                if (this.fpDate) this.fpDate.clear();
                if (timeSelect) {
                    timeSelect.value = '';
                    this.selectedTime = '';
                }
                return;
            }

            // switching to fixed => set default date (today)
            if (this.fpDate && !this.$refs.date.value) {
                const payload = this.getScheduleV2MethodPayload();
                if (payload?.next_available_date) {
                    this.fpDate.setDate(payload.next_available_date, true);
                } else {
                    const d = new Date();
                    const t = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    this.fpDate.setDate(t, true);
                }
            }

            // ensure intervals & auto-pick
            this.$nextTick(() => {
                this.updateAvailableTimeIntervals();
                this.$nextTick(() => {
                    if (!this.selectedTime) {
                        if (this.savedTime && this.availableTimeIntervals.includes(this.savedTime)) {
                            this.selectedTime = this.savedTime;
                        } else if (this.availableTimeIntervals.length) {
                            this.selectedTime = this.availableTimeIntervals[0];
                        }
                    }
                });
            });
        },
    };
}

/* =========================================================
 * Promos: available promos (radio group)
 * ======================================================= */

window.availablePromosComponent = function (initialSelected) {
    return {
        selected: initialSelected || 'none',

        change(value) {
            this.selected = value;
            this.apply();
        },

        apply() {
            fetch('/checkout/promo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ promo: this.selected }),
            })
                .then(r => r.json())
                .then(data => {
                    // guest => redirect to auth (your old behavior)
                    if (data.requires_auth) {
                        this.selected = 'none';

                        const noneRadio = document.querySelector('input[name="promo_radio"][value="none"]');
                        if (noneRadio) noneRadio.checked = true;

                        window.location.href = '/auth';
                        return;
                    }

                    if (!data.ok) return;

                    // discount
                    const discountEl = document.querySelector('[data-checkout-discount]');
                    if (discountEl) discountEl.textContent = data.discount_formatted;

                    // totals (big)
                    const totalUahEl = document.querySelector('[data-checkout-total-uah]');
                    const totalKopEl = document.querySelector('[data-checkout-total-kop]');
                    const bonusEarnEl = document.querySelector('[data-checkout-bonus-earn]');

                    if (totalUahEl) totalUahEl.textContent = data.total_uah_formatted ?? data.total_uah;
                    if (totalKopEl) totalKopEl.textContent = data.total_kop;
                    if (bonusEarnEl && data.bonus_earn !== undefined) bonusEarnEl.textContent = data.bonus_earn;

                    // re-render totals if needed
                    if (window.checkoutTotals && typeof window.checkoutTotals.setPromoDiscount === 'function') {
                        window.checkoutTotals.setPromoDiscount(0, { skipDeliveryRecalc: true });
                        if (data.shipping !== undefined) {
                            window.checkoutTotals.setShipping(Number(data.shipping || 0));
                        } else {
                            queueUnifiedDeliveryRecalc('available-promo-change-fallback');
                        }
                    } else if (window.checkoutTotals && typeof window.checkoutTotals.render === 'function') {
                        window.checkoutTotals.render();
                        if (data.shipping !== undefined) {
                            window.checkoutTotals.setShipping(Number(data.shipping || 0));
                        } else {
                            queueUnifiedDeliveryRecalc('available-promo-change-fallback');
                        }
                    }
                })
                .catch(() => {});
        },
    };
};

/* =========================================================
 * Promos: check conditions when shipping method changes
 *  (called from _shipping-toggle.blade.php)
 * ======================================================= */

window.checkPromoConditionsFromShipping = function () {
    // Пытаемся использовать метод из deliveryBlock, если доступен (он уже имеет debounce)
    const deliveryBlockEl = document.querySelector('[x-data*="deliveryBlock"]');
    if (deliveryBlockEl && window.Alpine && typeof Alpine.$data === 'function') {
        try {
            const deliveryBlock = Alpine.$data(deliveryBlockEl);
            if (deliveryBlock && typeof deliveryBlock.checkPromoConditions === 'function') {
                deliveryBlock.checkPromoConditions();
                return;
            }
        } catch (e) {
            // ignore Alpine access errors
        }
    }

    // Fallback: если deliveryBlock недоступен, используем отдельный debounce‑механизм
    window.__promoCheckTimer = window.__promoCheckTimer || null;
    window.__promoCheckInProgress = window.__promoCheckInProgress || false;

    // Если уже идет проверка, пропускаем
    if (window.__promoCheckInProgress) {
        return;
    }

    // Очищаем предыдущий таймер
    if (window.__promoCheckTimer) {
        clearTimeout(window.__promoCheckTimer);
    }

    window.__promoCheckTimer = setTimeout(() => {
        window.__promoCheckInProgress = true;

        const form = document.querySelector('[data-checkout-form]');
        if (!form) {
            window.__promoCheckInProgress = false;
            return;
        }

        const formData = new FormData(form);
        const shippingMethod = formData.get('shipping_method') || 'delivery';

        let deliveryMode = 'asap';
        let deliveryDate = null;
        let deliveryTime = null;

        if (deliveryBlockEl && window.Alpine && typeof Alpine.$data === 'function') {
            try {
                const deliveryBlock = Alpine.$data(deliveryBlockEl);
                if (deliveryBlock) {
                    deliveryMode = deliveryBlock.mode || 'asap';
                    deliveryDate = formData.get('delivery_date') || null;
                    deliveryTime = deliveryBlock.selectedTime || formData.get('delivery_time') || null;
                }
            } catch (e) {
                // ignore Alpine access errors
            }
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const checkUrl = document.querySelector('[data-check-promo-url]')?.dataset.checkPromoUrl ||
            '/checkout/check-promo-conditions';

        fetch(checkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                shipping_method: shippingMethod,
                delivery_mode: deliveryMode,
                delivery_date: deliveryDate,
                delivery_time: deliveryTime,
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.promos) {
                    window.dispatchEvent(new CustomEvent('update-promo-status', {
                        detail: data.promos,
                    }));
                }
            })
            .catch(() => {
                // молча игнорируем ошибку
            })
            .finally(() => {
                window.__promoCheckInProgress = false;
            });
    }, 300);
};

/* =========================================================
 * Promos: react to update-promo-status events
 * ======================================================= */

window.addEventListener('update-promo-status', (event) => {
    try {
        const promos = Array.isArray(event.detail) ? event.detail : [];
        if (!promos.length) return;

        const statusMap = {};
        promos.forEach((p) => {
            statusMap[String(p.value)] = !!p.is_active;
        });

        const labels = document.querySelectorAll('[data-promo-value]');

        labels.forEach((label) => {
            const value = label.getAttribute('data-promo-value');
            if (!value) return;

            const isActive = Object.prototype.hasOwnProperty.call(statusMap, value)
                ? statusMap[value]
                : false;

            // Обновляем Alpine-состояние, если компонент существует
            if (window.Alpine && typeof Alpine.$data === 'function') {
                try {
                    const data = Alpine.$data(label);
                    if (data && 'promoActive' in data) {
                        data.promoActive = isActive;
                    }
                } catch (e) {
                    // игнорируем ошибки доступа к Alpine
                }
            }

            // Фолбэк: прямое обновление disabled на радио,
            // чтобы даже без Alpine оно было некликабельно
            const radio = label.querySelector('input[type=\"radio\"]');
            if (radio) {
                radio.disabled = !isActive;
                if (isActive) {
                    radio.removeAttribute('disabled');
                } else {
                    radio.setAttribute('disabled', 'disabled');
                }
            }
        });
    } catch (e) {
        // в случае ошибки просто молча не обновляем промо
    }
});

/* =========================================================
 * Promos: coupon (promo code)
 * ======================================================= */

window.promoComponent = function () {
    return {
        coupon: '',
        applied: false,
        discount: 0,
        error: '',

        async apply() {
            this.error = '';
            this.applied = false;
            this.discount = 0;

            if (!this.coupon) {
                this.error = 'Введите промокод';
                return;
            }

            try {
                const response = await fetch('/checkout/apply-coupon', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ coupon: this.coupon }),
                });

                const res = await response.json();

                if (!res.ok) {
                    this.error = res.mess;
                    this.applied = false;
                    this.discount = 0;

                    // reset promo discount in totals
                    window.checkoutTotals?.setPromoDiscount?.(0);
                    return;
                }

                this.applied = true;
                this.discount = Number(res.discount || 0);

                window.checkoutTotals?.setPromoDiscount?.(this.discount);

            } catch (e) {
                this.error = 'Ошибка соединения';
            }
        },
    };
};

/* =========================================================
 * Totals: SINGLE source of truth
 * ======================================================= */

window.checkoutTotals = {
    promoDiscount: 0,
    shipping: 0,

    readBase() {
        return {
            sub: Money.parse(document.querySelector('[data-checkout-subtotal]')?.textContent),
            disc: Money.parse(document.querySelector('[data-checkout-discount]')?.textContent),
            bonus: Money.parse(document.querySelector('[data-checkout-bonus]')?.textContent),
        };
    },

    getDeliveryBase() {
        const { sub, disc, bonus } = this.readBase();

        return Math.max(sub - disc - bonus - (this.promoDiscount || 0), 0);
    },

    setShipping(v) {
        this.shipping = Number(v || 0);

        // hidden inputs (оба варианта)
        const ship1 = document.querySelector('[data-shipping-price-input]');
        const ship2 = document.getElementById('checkout-shipping-price');

        if (ship1) {
            ship1.value = String(this.shipping || 0);
            ship1.dispatchEvent(new Event('input', { bubbles: true }));
            ship1.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (ship2) {
            ship2.value = String(this.shipping || 0);
            ship2.dispatchEvent(new Event('input', { bubbles: true }));
            ship2.dispatchEvent(new Event('change', { bubbles: true }));
        }

        this.render();
    },


    setPromoDiscount(v, opts = {}) {
        this.promoDiscount = Number(v || 0);
        this.render();
        if (!opts.skipDeliveryRecalc) {
            queueUnifiedDeliveryRecalc('promo-discount-change');
        }
    },

    render() {
        // shipping line
        const shipEl = document.querySelector('[data-checkout-shipping]');
        if (shipEl) shipEl.textContent = Money.format(this.shipping);

        // hidden input (if exists)
        const shipInput = document.querySelector('[data-shipping-price-input]');
        if (shipInput) shipInput.value = String(this.shipping || 0);

        const total = this.getDeliveryBase() + (this.shipping || 0);
        window.checkoutPayparts?.scheduleUpdate?.(total);

        const uah = Math.floor(total);
        let kop = Math.round((total - uah) * 100);
        let u = uah;
        if (kop === 100) { u += 1; kop = 0; }

        const uahEl = document.querySelector('[data-checkout-total-uah]');
        if (uahEl) {
            uahEl.textContent = new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(u);
        }

        const kopEl = document.querySelector('[data-checkout-total-kop]');
        if (kopEl) kopEl.textContent = String(kop).padStart(2, '0');
    },
};

/* =========================================================
 * Layout: move blocks between columns (mobile/desktop)
 * ======================================================= */

function applyCheckoutLayout() {
    const mobileOrder = [
        'blk-items',
        'blk-toggle',
        'blk-contact',
        'blk-address',
        'blk-extras',
        'blk-conditions',
        'blk-promocode',
        'blk-promotions',
        'blk-bonus',
        'blk-totals',
        'blk-pay',
        'blk-submit',
        'blk-earned',
    ];

    const desktopLeft  = ['blk-contact','blk-address','blk-extras','blk-conditions','blk-promotions','blk-pay'];
    const desktopRight = ['blk-items','blk-promocode','blk-bonus','blk-totals','blk-submit','blk-earned'];

    const isMobile = window.matchMedia('(max-width: 1023px)').matches;
    const left   = document.getElementById('col-left');
    const right  = document.getElementById('col-right');
    const toggle = document.getElementById('blk-toggle');

    if (!left || !right || !toggle) return;

    if (isMobile) {
        right.style.display = 'none';
        mobileOrder.forEach(id => {
            const el = document.getElementById(id);
            if (el) left.appendChild(el);
        });
    } else {
        right.style.display = '';
        const colsWrap = left.parentElement;
        if (colsWrap && colsWrap.parentElement) colsWrap.parentElement.insertBefore(toggle, colsWrap);

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

/* =========================================================
 * Autosave checkout form
 * ======================================================= */

function bindCheckoutAutosave() {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    const saveUrl = window.CHECKOUT_CONFIG?.saveUrl;
    const csrf = window.CHECKOUT_CONFIG?.csrf;
    if (!saveUrl || !csrf) return;

    const saveFormData = debounce(() => {
        const payload = {
            contact_name: document.getElementById('contact_name')?.value || '',
            contact_phone: document.getElementById('contact_phone')?.value || '',
            contact_email: document.getElementById('contact_email')?.value || '',

            shipping_method: getShippingMethodValue(form),
            selected_address_id: getSelectedAddressRadio(document)?.value || document.querySelector('#selected_address_id')?.value || null,

            use_new_address: document.querySelector('[name="use_new_address"]')?.value
                || (document.querySelector('[name="use_new_address"]')?.checked ? 1 : 0)
                || 0,

            delivery_mode: form.querySelector('[name="delivery_mode"]')?.value || '',
            delivery_date: form.querySelector('[name="delivery_date"]')?.value || '',
            delivery_time: form.querySelector('[name="delivery_time"]')?.value || '',

            delivery_zone: (
                document.getElementById('checkout-delivery-zone')?.value ||
                form.querySelector('[data-delivery-zone-input]')?.value ||
                ''
            ),
            shipping_price: (
                document.getElementById('checkout-shipping-price')?.value ||
                form.querySelector('[data-shipping-price-input]')?.value ||
                '0'
            ),

            payment_method: form.querySelector('[name="payment_method"]:checked')?.value || '',
            selected_promo: form.querySelector('[name="selected_promo"]')?.value || 'none',

            comment_kitchen: form.querySelector('[name="comment_kitchen"]')?.value || '',
            comment_courier: form.querySelector('[name="comment_courier"]')?.value || '',

            addr_street: document.getElementById('checkout-address-street')?.value || '',
            addr_house: document.getElementById('checkout-address-house')?.value || '',
            addr_apartment: form.querySelector('[name="addr[apartment]"]')?.value || '',
            addr_intercom: form.querySelector('[name="addr[intercom]"]')?.value || '',
            addr_floor: form.querySelector('[name="addr[floor]"]')?.value || '',
            addr_porch: form.querySelector('[name="addr[porch]"]')?.value || '',
            addr_comment: form.querySelector('[name="addr[comment]"]')?.value || '',
            addr_is_private_house: form.querySelector('[name="addr[is_private_house]"]')?.checked ? '1' : '0',
            addr_type: form.querySelector('[name="addr[type]"]')?.value || '',
            addr_lat: document.getElementById('checkout-addr-lat')?.value || '',
            addr_lng: document.getElementById('checkout-addr-lng')?.value || '',
            addr_formatted_address: document.getElementById('checkout-address-formatted')?.value || '',
            addr_street_place_id: document.getElementById('checkout-address-place-id')?.value || '',

            use_bonus: form.querySelector('[name="use_bonus"]')?.checked ? '1' : '0',
            bonus_amount: form.querySelector('[name="bonus_amount"]')?.value || '0',
        };

        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        }).catch(() => {});
    }, 500);

    form.addEventListener('input', saveFormData);
    form.addEventListener('change', saveFormData);
    form.addEventListener('click', (e) => {
        if (e.target?.type === 'radio' || e.target?.type === 'checkbox') saveFormData();
    });
}

/* =========================================================
 * Google Maps loader (once)
 * ======================================================= */

function loadGoogleMapsOnce(cb) {
    if (window.google?.maps?.places && window.google?.maps?.geometry) return cb(true);

    window.__googleMapsLoading = window.__googleMapsLoading ?? false;
    window.__googleMapsLoaded = window.__googleMapsLoaded ?? false;

    if (window.__googleMapsLoaded) return cb(true);

    // already loading => poll
    if (window.__googleMapsLoading) {
        const t = setInterval(() => {
            if (window.__googleMapsLoaded || (window.google?.maps?.places && window.google?.maps?.geometry)) {
                clearInterval(t); cb(true);
            }
        }, 200);
        setTimeout(() => { clearInterval(t); cb(!!window.google?.maps); }, 10000);
        return;
    }

    const key = window.CHECKOUT_CONFIG?.googleMapsKey;
    if (!key) return cb(false);

    window.__googleMapsLoading = true;
    window.__onGoogleMapsLoaded = function() {
        window.__googleMapsLoaded = true;
        window.__googleMapsLoading = false;
        cb(true);
    };

    const s = document.createElement('script');
    s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=places,geometry&callback=__onGoogleMapsLoaded`;
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
}

/* =========================================================
 * Delivery polygons & shipping calc (ONE implementation)
 * ======================================================= */

window.ensureDeliveryPolygonsReady =
    window.ensureDeliveryPolygonsReady ||
    function ensureDeliveryPolygonsReady(cb) {
        const g = window.google;

        // 1) wait google fully
        if (!g || !g.maps || !g.maps.Polygon || !g.maps.geometry || !g.maps.geometry.poly) {
            dwarn('wait google...', {
                hasGoogle: !!g,
                hasMaps: !!g?.maps,
                hasPolygon: !!g?.maps?.Polygon,
                hasGeometry: !!g?.maps?.geometry?.poly,
            });
            return setTimeout(() => window.ensureDeliveryPolygonsReady(cb), 200);
        }

        // 2) wait deliveryAreas from map-cart.js
        if (!window.deliveryAreas) {
            dwarn('wait deliveryAreas... (map-cart.js not loaded yet?)');
            return setTimeout(() => window.ensureDeliveryPolygonsReady(cb), 200);
        }

        if (window.__deliveryPolygonsReady) return cb(true);

        function getZoneParams(zoneKey) {
            const zoneGroup = (zoneKey || '').split('_')[0];

            if (window.DELIVERY_ZONES && window.DELIVERY_ZONES[zoneGroup]) {
                const z = window.DELIVERY_ZONES[zoneGroup];
                return {
                    price: parseFloat(z.delivery_price) || 0,
                    free:  parseFloat(z.free_delivery_from) || 0,
                    color: z.color || (window.deliveryAreas[zoneKey]?.color) || '#000000',
                };
            }

            return {
                price: window.deliveryAreas[zoneKey]?.price || 0,
                free:  window.deliveryAreas[zoneKey]?.free || 0,
                color: window.deliveryAreas[zoneKey]?.color || '#000000',
            };
        }

        for (const key in window.deliveryAreas) {
            if (!Object.prototype.hasOwnProperty.call(window.deliveryAreas, key)) continue;

            const area = window.deliveryAreas[key];
            if (!area) continue;

            if (!area.polygon) {
                area.polygon = new g.maps.Polygon({
                    path: area.area,
                    geodesic: true,
                    map: null,
                });
            }

            const params = getZoneParams(key);
            area.price = params.price;
            area.free  = params.free;
            if (params.color) area.color = params.color;
        }

        window.__deliveryPolygonsReady = true;
        cb(true);
    };

/**
 * Returns delivery zone key by matching polygon references (optional helper)
 */
function inferAreaKey(areaObj) {
    if (!areaObj || !window.deliveryAreas) return null;
    if (areaObj.key) return areaObj.key;

    if (areaObj.polygon) {
        for (const k in window.deliveryAreas) {
            if (window.deliveryAreas[k]?.polygon === areaObj.polygon) return k;
        }
    }

    if (areaObj.area) {
        for (const k in window.deliveryAreas) {
            if (window.deliveryAreas[k]?.area === areaObj.area) return k;
        }
    }

    return null;
}

/**
 * Calculates shipping by coords using delivery polygons + zones config.
 * Uses totals from DOM + checkoutTotals.promoDiscount
 */
function calcShippingByCoords(lat, lng) {
 //   dlog('calcShippingByCoords start', { lat, lng });

    return new Promise((resolve) => {
        window.ensureDeliveryPolygonsReady((ok) => {
            if (!ok) return resolve({ shipping: 0, zone: '' });

            const latN = parseFloat(lat), lngN = parseFloat(lng);
            if (isNaN(latN) || isNaN(lngN)) return resolve({ shipping: 0, zone: '' });

            const g = window.google;
            const area = window.resolveAreaByLatLng?.(new g.maps.LatLng(latN, lngN));
            if (!area) {
                dwarn('resolveAreaByLatLng returned null - point outside polygons or function missing');
                return resolve({ shipping: 0, zone: '' });
            }

            const rawKey = inferAreaKey(area); // e.g. Brown_2
            const group = rawKey ? rawKey.split('_')[0] : null; // Brown
            const z = group ? window.DELIVERY_ZONES?.[group] : null;

            // if zones config missing - fallback to area.price/free
            const freeFrom = z ? (parseFloat(z.free_delivery_from) || 0) : (parseFloat(area.free) || 0);
            const price    = z ? (parseFloat(z.delivery_price) || 0) : (parseFloat(area.price) || 0);
            const zoneName = z ? (z.name || group) : (group || '');

            const base = window.checkoutTotals?.getDeliveryBase?.() ?? 0;
            const shipping = (freeFrom > 0 && base >= freeFrom) ? 0 : price;
     //       dlog('shipping result', { freeFrom, price, base, shipping, zoneName, rawKey, group });

            resolve({ shipping, zone: zoneName || '' });
        });
    });
}

function bindDeliveryRecalc() {
    // saved addresses (radio)
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (t && t.matches('input[name="selected_address_id"]')) {
            queueUnifiedDeliveryRecalc('selected-address-change');
        }
    });

    // initial recalc
    const checked = getSelectedAddressRadio(document);
    if (checked) {
        queueUnifiedDeliveryRecalc('initial-selected-address');
    } else {
        window.checkoutTotals.render();
    }

    // new address (if hidden lat/lng fields exist)
    const latEl = document.getElementById('checkout-addr-lat');
    const lngEl = document.getElementById('checkout-addr-lng');
    const streetEl = document.getElementById('checkout-address-street');
    const houseEl = document.getElementById('checkout-address-house');
    const cityEl = document.getElementById('checkout-address-city');

    const triggerNew = () => {
        const useNew = document.querySelector('[name="use_new_address"]')?.value === '1';
        const method = getShippingMethodValue(document) === 'delivery';
        if (!useNew || !method) return;

        const lat = latEl?.value;
        const lng = lngEl?.value;
        if (!lat || !lng) return;

        queueUnifiedDeliveryRecalc('new-address-coords-change');
    };

    if (latEl && lngEl) {
        latEl.addEventListener('input', triggerNew);
        lngEl.addEventListener('input', triggerNew);
        latEl.addEventListener('change', triggerNew);
        lngEl.addEventListener('change', triggerNew);
    }

    [streetEl, houseEl, cityEl].forEach((el) => {
        if (!el) return;
        el.addEventListener('input', () => {
            const useNew = document.querySelector('[name="use_new_address"]')?.value === '1';
            const method = getShippingMethodValue(document) === 'delivery';
            if (useNew && method) queueUnifiedDeliveryRecalc('new-address-text-input');
        });
        el.addEventListener('change', () => {
            const useNew = document.querySelector('[name="use_new_address"]')?.value === '1';
            const method = getShippingMethodValue(document) === 'delivery';
            if (useNew && method) queueUnifiedDeliveryRecalc('new-address-text-change');
        });
    });

    document.addEventListener('input', (e) => {
        const t = e.target;
        if (!t) return;

        if (t.matches('[name="bonus_amount"]')) {
            queueUnifiedDeliveryRecalc('bonus-amount-input');
        }
    });

    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t) return;

        if (t.matches('[name="shipping_method"]')) {
            const shippingMethod = t.value || 'delivery';
            if (shippingMethod !== 'delivery') {
                applyShippingResult({ shipping: 0, zone: '' });
            } else {
                queueUnifiedDeliveryRecalc('shipping-method-change');
            }
        }

        if (t.matches('[name="use_bonus"], [name="bonus_amount"]')) {
            queueUnifiedDeliveryRecalc('bonus-change');
        }
    });
}

/**
 * Обработка выбора сохранённого адреса:
 * - если есть координаты в data-атрибутах — сразу считаем доставку
 * - если координат нет — запрашиваем у Google по строке адреса, сохраняем в БД и считаем доставку
 */
function handleSavedAddressChange(radio, token = null) {
    const latRaw = radio.dataset.lat;
    const lngRaw = radio.dataset.lng;

    if (latRaw && lngRaw) {
        calcShippingByCoords(latRaw, lngRaw).then((res) => applyShippingResult(res, token));
        return;
    }

    // координат нет — пробуем получить через Geocoder
    if (!window.google || !window.google.maps || !google.maps.Geocoder) {
        // Если Google Maps ещё не загружен, грузим его и повторяем попытку
        if (typeof loadGoogleMapsOnce === 'function') {
            loadGoogleMapsOnce((ok) => {
                if (ok) {
                    handleSavedAddressChange(radio, token);
                } else {
                    applyShippingResult({ shipping: 0, zone: '' }, token);
                }
            });
        } else {
            // без Google Maps не можем посчитать доставку
            applyShippingResult({ shipping: 0, zone: '' }, token);
        }
        return;
    }

    const parseJson = (v) => {
        if (!v) return '';
        try { return JSON.parse(v); } catch (e) { return v; }
    };

    const street = parseJson(radio.dataset.street) || '';
    const house  = parseJson(radio.dataset.house)  || '';
    const city   = parseJson(radio.dataset.city)   || 'Київ';

    // Основной вариант: улица + дом + город
    let addressString = [street, house, city].filter(Boolean).join(', ');

    // Фолбэк: если вдруг street/house пустые, используем line + city
    if (!addressString) {
        const line = parseJson(radio.dataset.line) || '';
        addressString = [line, city].filter(Boolean).join(', ');
    }

    if (!addressString) {
        applyShippingResult({ shipping: 0, zone: '' }, token);
        return;
    }

    // Используем Places API (как при сохранении нового адреса через Autocomplete)
    if (!google.maps.places || !google.maps.places.PlacesService) {
        applyShippingResult({ shipping: 0, zone: '' }, token);
        return;
    }

    const service =
        window.__checkoutPlacesService ||
        (window.__checkoutPlacesService = new google.maps.places.PlacesService(document.createElement('div')));

    service.findPlaceFromQuery(
        {
            query: addressString,
            fields: ['geometry', 'formatted_address'],
            language: 'uk',
        },
        (results, status) => {
            if (
                status !== google.maps.places.PlacesServiceStatus.OK ||
                !results ||
                !results.length ||
                !results[0].geometry ||
                !results[0].geometry.location
            ) {
                applyShippingResult({ shipping: 0, zone: '' }, token);
                return;
            }

            const loc = results[0].geometry.location;
            const latVal = typeof loc.lat === 'function' ? loc.lat() : loc.lat;
            const lngVal = typeof loc.lng === 'function' ? loc.lng() : loc.lng;

            if (latVal == null || lngVal == null) {
                applyShippingResult({ shipping: 0, zone: '' }, token);
                return;
            }

            const lat = String(latVal);
            const lng = String(lngVal);

            // Обновляем data-атрибуты на radio, чтобы дальше всё работало как обычно
            radio.dataset.lat = lat;
            radio.dataset.lng = lng;

            // Пытаемся сохранить координаты в БД
            const id = radio.value;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            fetch(`/profile/addresses/${encodeURIComponent(id)}/coords`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    latitude: latVal,
                    longitude: lngVal,
                    formatted_address: results[0].formatted_address || null,
                }),
            }).catch(() => {
                // Ошибку сохранения координат игнорируем — важно, что на этот заказ доставка посчитается
            });

            // Теперь можем посчитать доставку по свежим координатам
            calcShippingByCoords(lat, lng).then((res) => applyShippingResult(res, token));
        }
    );
}

function getNewAddressDeliveryParts() {
    const street = document.getElementById('checkout-address-street')?.value?.trim() || '';
    const house = document.getElementById('checkout-address-house')?.value?.trim() || '';
    const city = document.getElementById('checkout-address-city')?.value?.trim() || 'Kyiv';
    const streetParts = street.split(',').map((part) => part.trim()).filter(Boolean);
    const streetName = streetParts.shift() || street;
    const normalizedParts = [];

    [streetName && [streetName, house].filter(Boolean).join(' '), ...streetParts, city].forEach((part) => {
        const normalized = String(part || '').toLowerCase().replace(/\s+/g, ' ').trim();
        if (!normalized) return;
        if (normalizedParts.some((item) => item.normalized === normalized)) return;
        normalizedParts.push({ value: part, normalized });
    });

    return {
        street,
        house,
        city,
        query: normalizedParts.map((item) => item.value).join(', '),
    };
}

function applyNewAddressGeocodeResult(result) {
    const loc = result?.geometry?.location;
    if (!loc) return null;

    const latVal = typeof loc.lat === 'function' ? loc.lat() : loc.lat;
    const lngVal = typeof loc.lng === 'function' ? loc.lng() : loc.lng;

    if (latVal == null || lngVal == null) return null;

    const lat = String(latVal);
    const lng = String(lngVal);
    const latEl = document.getElementById('checkout-addr-lat');
    const lngEl = document.getElementById('checkout-addr-lng');
    const formattedEl = document.getElementById('checkout-address-formatted');
    const placeIdEl = document.getElementById('checkout-address-place-id');

    if (latEl) {
        latEl.value = lat;
        latEl.dispatchEvent(new Event('input', { bubbles: true }));
        latEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (lngEl) {
        lngEl.value = lng;
        lngEl.dispatchEvent(new Event('input', { bubbles: true }));
        lngEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (formattedEl && result.formatted_address) {
        formattedEl.value = result.formatted_address;
    }

    if (placeIdEl && result.place_id) {
        placeIdEl.value = result.place_id;
    }

    return { lat, lng };
}

function calcShippingForExistingNewAddressCoords(token) {
    const lat = document.getElementById('checkout-addr-lat')?.value;
    const lng = document.getElementById('checkout-addr-lng')?.value;

    if (!lat || !lng) {
        applyShippingResult({ shipping: 0, zone: '' }, token);
        return;
    }

    calcShippingByCoords(lat, lng).then((res) => applyShippingResult(res, token));
}

function geocodeNewAddressForDelivery(token) {
    const { street, house, query } = getNewAddressDeliveryParts();

    if (!street || !house || !query) return false;

    window.__checkoutNewAddressGeocode = window.__checkoutNewAddressGeocode || {
        query: '',
        lat: '',
        lng: '',
    };

    const cached = window.__checkoutNewAddressGeocode;
    const currentLat = document.getElementById('checkout-addr-lat')?.value || '';
    const currentLng = document.getElementById('checkout-addr-lng')?.value || '';

    if (cached.query === query && currentLat && currentLng && cached.lat === currentLat && cached.lng === currentLng) {
        calcShippingByCoords(currentLat, currentLng).then((res) => applyShippingResult(res, token));
        return true;
    }

    if (!window.google || !window.google.maps || !google.maps.places || !google.maps.places.PlacesService) {
        if (typeof loadGoogleMapsOnce === 'function') {
            loadGoogleMapsOnce((ok) => {
                if (ok) {
                    geocodeNewAddressForDelivery(token);
                } else {
                    calcShippingForExistingNewAddressCoords(token);
                }
            });
            return true;
        }

        return false;
    }

    const service =
        window.__checkoutPlacesService ||
        (window.__checkoutPlacesService = new google.maps.places.PlacesService(document.createElement('div')));

    service.findPlaceFromQuery(
        {
            query,
            fields: ['geometry', 'formatted_address', 'place_id'],
            language: 'uk',
        },
        (results, status) => {
            if (
                status !== google.maps.places.PlacesServiceStatus.OK ||
                !results ||
                !results.length ||
                !results[0].geometry ||
                !results[0].geometry.location
            ) {
                calcShippingForExistingNewAddressCoords(token);
                return;
            }

            const coords = applyNewAddressGeocodeResult(results[0]);
            if (!coords) {
                calcShippingForExistingNewAddressCoords(token);
                return;
            }

            window.__checkoutNewAddressGeocode = {
                query,
                lat: coords.lat,
                lng: coords.lng,
            };

            calcShippingByCoords(coords.lat, coords.lng).then((res) => applyShippingResult(res, token));
        }
    );

    return true;
}

function applyShippingResult({ shipping, zone }, token = null) {
    if (token !== null && token !== undefined) {
        if (token !== window.__deliveryRecalcToken) return;
    }

    const zoneEl1 = document.querySelector('[data-delivery-zone-input]');
    const zoneEl2 = document.getElementById('checkout-delivery-zone');

    if (zoneEl1) {
        zoneEl1.value = zone || '';
        zoneEl1.dispatchEvent(new Event('input', { bubbles: true }));
        zoneEl1.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (zoneEl2) {
        zoneEl2.value = zone || '';
        zoneEl2.dispatchEvent(new Event('input', { bubbles: true }));
        zoneEl2.dispatchEvent(new Event('change', { bubbles: true }));
    }

    window.checkoutTotals.setShipping(shipping);
}

window.recalculateCheckoutDelivery = function () {
    window.__deliveryRecalcToken = (window.__deliveryRecalcToken || 0) + 1;
    const token = window.__deliveryRecalcToken;

    const shippingMethod = getShippingMethodValue(document);

    if (shippingMethod !== 'delivery') {
        applyShippingResult({ shipping: 0, zone: '' }, token);
        return;
    }

    const selectedAddress = getSelectedAddressRadio(document);
    if (selectedAddress) {
        handleSavedAddressChange(selectedAddress, token);
        return;
    }

    const useNew = document.querySelector('[name="use_new_address"]')?.value === '1';
    const lat = document.getElementById('checkout-addr-lat')?.value;
    const lng = document.getElementById('checkout-addr-lng')?.value;

    if (useNew && geocodeNewAddressForDelivery(token)) {
        return;
    }

    if (useNew && lat && lng) {
        calcShippingByCoords(lat, lng).then((res) => applyShippingResult(res, token));
        return;
    }

    applyShippingResult({ shipping: 0, zone: '' }, token);
};

const scheduleCheckoutDeliveryRecalc = (() => {
    let timer = null;

    return () => {
        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            timer = null;
            window.recalculateCheckoutDelivery?.();
        }, 150);
    };
})();

function queueUnifiedDeliveryRecalc(_reason = 'unknown') {
    scheduleCheckoutDeliveryRecalc();
}

/**
 * Public helper for manual recalc (kept from your file)
 */
window.checkoutDeliveryRecalc = function () {
    window.recalculateCheckoutDelivery?.();
};

/* =========================================================
 * Address autocomplete (your integration)
 * ======================================================= */

/* =========================================================
 * Address autocomplete (checkout)
 * ======================================================= */

function initCheckoutAutocomplete() {
    const latEl  = document.getElementById('checkout-addr-lat');
    const lngEl  = document.getElementById('checkout-addr-lng');
    const cityEl = document.querySelector('#checkout-address-city');
    const formattedEl = document.getElementById('checkout-address-formatted');
    const placeIdEl = document.getElementById('checkout-address-place-id');

    if (!latEl || !lngEl) {
        if (CHECKOUT_DEBUG) console.warn('[checkout] lat/lng hidden inputs not found:', { latEl, lngEl });
    }

    // На мобильных устройствах используем максимально простой Autocomplete,
    // без нашей сложной обёртки, чтобы избежать конфликтов с фокусом/клавиатурой.
    const isTouchDevice =
        typeof window !== 'undefined' &&
        ('ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0);

    const useNativeMobileAutocomplete = false;

    if (useNativeMobileAutocomplete && isTouchDevice) {
        loadGoogleMapsOnce((ok) => {
            if (!ok || !window.google?.maps?.places) return;

                const setupMobileAutocomplete = () => {
                    const streetInput = document.getElementById('checkout-address-street');
                    const houseInput  = document.getElementById('checkout-address-house');
                    if (!streetInput) return;

                    let selectedStreetValue = '';
                    let isPlaceSelected = false;
                    let isSelectingFromGoogle = false;
                    let lastGoogleSelectionAt = 0;

                    const normalizeAddressValue = (value) => {
                        return String(value || '')
                            .toLowerCase()
                            .replace(/[.,]/g, ' ')
                            .replace(/\s+/g, ' ')
                            .trim();
                    };

                    const isAddressSelectionMatch = (currentValue, selectedValue) => {
                        const currentNorm = normalizeAddressValue(currentValue);
                        const selectedNorm = normalizeAddressValue(selectedValue);

                        if (!currentNorm || !selectedNorm) return false;

                        return currentNorm === selectedNorm ||
                            currentNorm.includes(selectedNorm) ||
                            selectedNorm.includes(currentNorm);
                    };

                    const showManualInputError = () => {
                        if (typeof window.showAddressErrorModal === 'function') {
                            window.showAddressErrorModal('Будь ласка, оберіть адресу зі списку Google. Ручне введення адреси не дозволено.');
                        }
                    };

                    const hasValidSelectedCoords = () => {
                        const lat = parseFloat(latEl?.value || '');
                        const lng = parseFloat(lngEl?.value || '');
                        return !Number.isNaN(lat) && !Number.isNaN(lng);
                    };

                    const clearSelectedCoords = () => {
                        if (latEl) {
                            latEl.value = '';
                            latEl.dispatchEvent(new Event('input', { bubbles: true }));
                            latEl.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (lngEl) {
                            lngEl.value = '';
                            lngEl.dispatchEvent(new Event('input', { bubbles: true }));
                            lngEl.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    };

                // Пытаемся ограничить подсказки только зонами доставки:
                // строим bounds по всем полигонам из deliveryAreas.
                let bounds = null;
                try {
                    const g = window.google;
                    const areas = window.deliveryAreas;
                    if (areas && g?.maps?.LatLngBounds) {
                        const b = new g.maps.LatLngBounds();
                        Object.values(areas).forEach((area) => {
                            (area?.area || []).forEach((pt) => {
                                if (pt && typeof pt.lat === 'number' && typeof pt.lng === 'number') {
                                    b.extend(new g.maps.LatLng(pt.lat, pt.lng));
                                }
                            });
                        });
                        // Проверяем, что bounds не пустой
                        if (b.getNorthEast && b.getSouthWest && !b.isEmpty?.()) {
                            bounds = b;
                        }
                    }
                } catch (_) {
                    // в случае ошибки просто не задаём bounds
                }

                const acOptions = {
                    componentRestrictions: { country: 'ua' },
                    types: ['address'],
                };
                if (bounds) {
                    acOptions.bounds = bounds;
                    acOptions.strictBounds = true;
                }

                const ac = new google.maps.places.Autocomplete(streetInput, acOptions);

                ac.addListener('place_changed', () => {
                    try {
                        isSelectingFromGoogle = true;
                        const place = ac.getPlace();
                        const loc = place?.geometry?.location;
                        if (!loc) {
                            isSelectingFromGoogle = false;
                            return;
                        }

                        // Координаты для доставки
                        const lat = (typeof loc.lat === 'function') ? loc.lat() : loc.lat;
                        const lng = (typeof loc.lng === 'function') ? loc.lng() : loc.lng;

                        if (latEl) {
                            latEl.value = String(lat);
                            latEl.dispatchEvent(new Event('input', { bubbles: true }));
                            latEl.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (lngEl) {
                            lngEl.value = String(lng);
                            lngEl.dispatchEvent(new Event('input', { bubbles: true }));
                            lngEl.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        // Попробуем заполнить дом и город из компонентов адреса
                        const comps = place.address_components || [];
                        let streetNumber = '';
                        let city = '';
                        for (const c of comps) {
                            if (c.types.includes('street_number')) streetNumber = c.long_name;
                            if (c.types.includes('locality')) city = c.long_name;
                        }

                        if (houseInput && streetNumber) {
                            houseInput.value = streetNumber;
                            houseInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                        if (cityEl && city) {
                            cityEl.value = city;
                            cityEl.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        setTimeout(() => {
                            const value = streetInput.value || place?.formatted_address || '';
                            selectedStreetValue = value;
                            isPlaceSelected = !!value;
                            if (value) {
                                lastGoogleSelectionAt = Date.now();
                            }
                            isSelectingFromGoogle = false;
                        }, 50);
                    } catch (e) {
                        console.error('[checkout] mobile autocomplete error', e);
                        isSelectingFromGoogle = false;
                    }
                });

                streetInput.addEventListener('input', (e) => {
                    if (isSelectingFromGoogle) return;

                    const currentValue = e.target.value;
                    if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                        isPlaceSelected = false;
                        selectedStreetValue = '';
                        clearSelectedCoords();
                    }
                });

                streetInput.addEventListener('blur', (e) => {
                    setTimeout(() => {
                        if (isSelectingFromGoogle) return;

                        const currentValue = e.target.value;
                        if (!currentValue || currentValue.trim() === '') return;

                        if (hasValidSelectedCoords()) {
                            isPlaceSelected = true;
                            if (!selectedStreetValue) selectedStreetValue = currentValue;
                            return;
                        }

                        if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                            isPlaceSelected = true;
                            return;
                        }

                        if (Date.now() - lastGoogleSelectionAt < 1200) return;

                        if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                            e.target.value = selectedStreetValue;
                            return;
                        }

                        if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                            e.target.value = '';
                            showManualInputError();
                        }
                    }, 300);
                });

                streetInput.addEventListener('paste', (e) => {
                    if (isPlaceSelected && selectedStreetValue) {
                        setTimeout(() => {
                            if (!isAddressSelectionMatch(streetInput.value, selectedStreetValue)) {
                                streetInput.value = selectedStreetValue;
                                showManualInputError();
                            }
                        }, 0);
                        return;
                    }

                    e.preventDefault();
                    showManualInputError();
                });

                const form = streetInput.closest('form');
                if (form) {
                    form.addEventListener('submit', (e) => {
                        const shippingMethod = getShippingMethodValue(form);
                        if (shippingMethod === 'pickup' || streetInput.disabled) {
                            return;
                        }

                        const currentValue = streetInput.value;

                        if (hasValidSelectedCoords()) {
                            isPlaceSelected = true;
                            if (!selectedStreetValue) selectedStreetValue = currentValue;
                            return;
                        }

                        if (selectedStreetValue && isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                            isPlaceSelected = true;
                            return;
                        }

                        if (!isPlaceSelected && currentValue && currentValue.trim() !== '') {
                            e.preventDefault();
                            e.stopPropagation();
                            showManualInputError();
                            streetInput.focus();
                            return false;
                        }

                        if (isPlaceSelected && selectedStreetValue && !isAddressSelectionMatch(currentValue, selectedStreetValue)) {
                            e.preventDefault();
                            e.stopPropagation();
                            streetInput.value = selectedStreetValue;
                            showManualInputError();
                            streetInput.focus();
                            return false;
                        }
                    }, true);
                }
            };

            // Ждём, пока загрузятся полигоны из map-cart.js, чтобы посчитать bounds
            if (typeof window.ensureDeliveryPolygonsReady === 'function') {
                window.ensureDeliveryPolygonsReady(() => {
                    setupMobileAutocomplete();
                });
            } else {
                setupMobileAutocomplete();
            }
        });

        return;
    }

    if (typeof window.initAddressAutocomplete === 'undefined') {
        if (CHECKOUT_DEBUG) console.warn('[checkout] initAddressAutocomplete is undefined');
        return;
    }

    // Десктоп: используем расширенную обёртку с фильтрацией по зонам доставки.
    window.initAddressAutocomplete({
        streetInputId: 'checkout-address-street',
        houseInputId: 'checkout-address-house',
        cityInputSelector: '#checkout-address-city',

        kyivOnly: true,
        filterByDeliveryZone: true,
        googleMapsKey: window.CHECKOUT_CONFIG?.googleMapsKey,

        onPlaceSelected: function (data) {
            try {
                const place = data?.place;
                const loc = place?.geometry?.location;

                if (!loc) {
                    if (CHECKOUT_DEBUG) console.warn('[checkout] onPlaceSelected: no geometry.location', data);
                    return;
                }

                const lat = (typeof loc.lat === 'function') ? loc.lat() : loc.lat;
                const lng = (typeof loc.lng === 'function') ? loc.lng() : loc.lng;

                if (latEl) {
                    latEl.value = String(lat);
                    latEl.dispatchEvent(new Event('input', { bubbles: true }));
                    latEl.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (lngEl) {
                    lngEl.value = String(lng);
                    lngEl.dispatchEvent(new Event('input', { bubbles: true }));
                    lngEl.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const selectedAddress = (data?.street || place?.formatted_address || '').trim();
                if (selectedAddress && data?.streetInput) {
                    data.streetInput.value = selectedAddress;
                    data.streetInput.dispatchEvent(new Event('input', { bubbles: true }));
                    data.streetInput.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (formattedEl) {
                    formattedEl.value = (place?.formatted_address || selectedAddress).trim();
                    formattedEl.dispatchEvent(new Event('input', { bubbles: true }));
                    formattedEl.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (placeIdEl) {
                    placeIdEl.value = place?.place_id || '';
                    placeIdEl.dispatchEvent(new Event('input', { bubbles: true }));
                    placeIdEl.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } catch (e) {
                console.error('[checkout] onPlaceSelected error', e);
            }
        },
    });
}



/* =========================================================
 * New Address: reset helper (kept)
 * ======================================================= */

window.resetNewAddress = function(btn){
    const form = btn.closest('form');
    if (!form) return;

    [
        'addr[street]','addr[house]','addr[apartment]','addr[porch]',
        'addr[intercom]','addr[floor]','addr[comment]',
        'addr[formatted_address]','addr[street_place_id]'
    ].forEach((name) => {
        const el = form.querySelector('[name="'+name+'"]');
        if (el) {
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });

    const priv = form.querySelector('[name="addr[is_private_house]"]');
    if (priv) priv.checked = false;

    form.querySelectorAll('.tp-error').forEach(p => p.classList.add('hidden'));
    form.querySelectorAll('.tp-float-wrap.is-invalid').forEach(w => w.classList.remove('is-invalid'));
};

/* =========================================================
 * Required validation (data-required + data-required-if)
 * NOTE: capture=true to run before guest-auth submit handler
 * ======================================================= */

function initCheckoutRequiredValidation() {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    function esc(value) {
        const str = String(value || '');
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(str);
        }
        return str.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function getFieldValue(form, name) {
        const els = form.querySelectorAll('[name="' + esc(name) + '"]');
        if (!els.length) return '';

        const first = els[0];
        if (first.type === 'radio') {
            const checked = form.querySelector('[name="' + esc(name) + '"]:checked');
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

    function showError(form, name) {
        const err = form.querySelector('[data-error-for="'+esc(name)+'"]');
        if (err) err.classList.remove('hidden');

        const wrap = form.querySelector('[data-field-wrap="'+esc(name)+'"]');
        if (wrap) {
            wrap.classList.add('is-invalid');
            const floatWrap = wrap.querySelector('.tp-float-wrap');
            if (floatWrap) floatWrap.classList.add('is-invalid');
        }

        form.querySelectorAll('[name="'+esc(name)+'"]').forEach((el) => {
            el.classList.add('is-invalid');
        });

        const byId = document.getElementById(name);
        if (byId) byId.classList.add('is-invalid');
    }

    function clearError(form, name) {
        const err = form.querySelector('[data-error-for="'+esc(name)+'"]');
        if (err) err.classList.add('hidden');

        const wrap = form.querySelector('[data-field-wrap="'+esc(name)+'"]');
        if (wrap) {
            wrap.classList.remove('is-invalid');
            const floatWrap = wrap.querySelector('.tp-float-wrap');
            if (floatWrap) floatWrap.classList.remove('is-invalid');
        }

        form.querySelectorAll('[name="'+esc(name)+'"]').forEach((el) => {
            el.classList.remove('is-invalid');
        });

        const byId = document.getElementById(name);
        if (byId) byId.classList.remove('is-invalid');
    }

    function focusField(form, name) {
        const escaped = esc(name);
        const wrap = form.querySelector('[data-field-wrap="' + escaped + '"]');

        let focusEl = wrap?.querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])');

        if (!focusEl) {
            focusEl = form.querySelector('[name="' + escaped + '"]:not([type="hidden"]):not([disabled])');
        }

        if (!focusEl && name === 'delivery_date') {
            focusEl = form.querySelector('[name="delivery_date"]') || null;
        }

        const scrollTarget = wrap || focusEl || document.getElementById(name);
        if (!scrollTarget) return;

        scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (focusEl && typeof focusEl.focus === 'function') {
            setTimeout(() => focusEl.focus(), 150);
        }
    }

    function validateForm(form) {
        // clear all
        form.querySelectorAll('[data-error-for]').forEach(p => p.classList.add('hidden'));
        form.querySelectorAll('.tp-float-wrap.is-invalid').forEach(w => w.classList.remove('is-invalid'));
        form.querySelectorAll('.is-invalid').forEach(w => w.classList.remove('is-invalid'));

        let firstInvalidName = null;

        const requiredFields = form.querySelectorAll('[data-required]');
        requiredFields.forEach(field => {
            if (field.disabled) return;
            if (!shouldValidate(form, field)) return;

            const name = field.getAttribute('name') || field.getAttribute('id');
            if (!name) return;

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

    form.addEventListener('submit', function (e) {
        if (!validateForm(form)) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation?.();
        }
    }, true);

    // clear error on input/change
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
}


/* =========================================================
 * Guest checkout: submit shows auth modal (kept)
 * ======================================================= */

function initGuestSubmitAuthIntercept() {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    const isGuest = (window.isGuestCheckout === true || window.isGuestCheckout === 'true');
    if (!isGuest) return;

    form.addEventListener('submit', (e) => {
        // If HTML5 validation fails, this handler won't run.
        e.preventDefault();

        // Save checkout URL in session (non-critical)
        fetch('/auth/save-checkout-url', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ url: window.location.href }),
        }).catch(() => {});

        // show auth modal
        window.dispatchEvent(new CustomEvent('show-auth-modal', {
            detail: {
                message: 'Щоб оформити замовлення, увійдіть або зареєструйтесь.',
            },
        }));
    });
}

/* =========================================================
 * Alpine registration
 * ======================================================= */

/*function registerCheckoutAlpine() {
    Alpine.data('deliveryBlock', window.deliveryBlock);
    Alpine.data('tooltip', (text) => tooltip(text));
    // availablePromosComponent is used as x-data="availablePromosComponent('...')"
}

if (window.Alpine) {
    registerCheckoutAlpine();
} else {
    window.addEventListener('alpine:init', registerCheckoutAlpine);
}*/


/* =========================================================
 * Boot (safe)
 * ======================================================= */

function onReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
        fn();
    }
}

onReady(() => {
  // console.log('[checkout] boot start');

    const isTouchDevice =
        typeof window !== 'undefined' &&
        ('ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0);

    // responsive blocks layout
    applyCheckoutLayout();
    // На десктопе можно безопасно пересобирать колонки при resize.
    // На Android появление/скрытие клавиатуры тоже вызывает resize и
    // перетасовка DOM в applyCheckoutLayout может сбрасывать фокус,
    // поэтому на touch‑устройствах resize‑листенер не вешаем.
    if (!isTouchDevice) {
        window.addEventListener('resize', applyCheckoutLayout);
    }

    // form autosave
    bindCheckoutAutosave();

    // validation hooks (если у тебя есть функция)
    // ✅ REQUIRED validation (твоя рабочая)
    if (typeof initCheckoutRequiredValidation === 'function') {
        initCheckoutRequiredValidation();
    }

// ✅ guest intercept (если используешь)
    if (typeof initGuestSubmitAuthIntercept === 'function') {
        initGuestSubmitAuthIntercept();
    }


    // google autocomplete init
    if (typeof initCheckoutAutocomplete === 'function') {
        initCheckoutAutocomplete();
    }

    // пересчёт доставки / подписки на изменения (если есть)
    if (typeof bindDeliveryRecalc === 'function') {
        bindDeliveryRecalc();
    }
  //  initCheckoutValidationSafe();

 //   console.log('[checkout] boot done');
});
