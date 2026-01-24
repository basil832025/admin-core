// resources/js/checkout.js
//import flatpickr from 'flatpickr';
//import 'flatpickr/dist/flatpickr.css';

/* ===== Alpine components for checkout page ===== */
function tooltip(text = '') {
    return {
        open: false,
        text,
        toggle() { this.open = !this.open; },
        show()   { this.open = true; },
        hide()   { this.open = false; },
    };
}

function deliveryBlock() {
    return {
        mode: 'asap',
        fpDate: null,
        allTimeIntervals: [],
        availableTimeIntervals: [],
        selectedTime: '',
        savedTime: '',

        // сколько минут “подготовки” (у тебя было +60)
        leadMinutes: 60,

        init() {
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

            const parseStartMinutes = (interval) => {
                // "09:00-09:15" -> 540
                const m = String(interval || '').match(/^(\d{2}):(\d{2})-/);
                if (!m) return null;
                return parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
            };

            const autoPickTimeIfNeeded = () => {
                if (this.mode !== 'fixed') return;

                // если пользователь уже выбрал — не трогаем
                if (this.selectedTime) return;

                // если есть сохранённое и оно доступно — ставим его
                if (this.savedTime && this.availableTimeIntervals.includes(this.savedTime)) {
                    this.selectedTime = this.savedTime;
                    return;
                }

                // иначе ставим первый доступный
                if (this.availableTimeIntervals.length) {
                    this.selectedTime = this.availableTimeIntervals[0];
                }
            };

            const moveToTomorrowIfNoIntervalsToday = () => {
                // если сегодня и после фильтрации пусто — переносим дату на завтра
                if (!this.fpDate) return;

                const sel = this.fpDate.selectedDates?.[0];
                if (!sel) return;

                const today = new Date();
                today.setHours(0,0,0,0);
                const sel0 = new Date(sel);
                sel0.setHours(0,0,0,0);

                if (sel0.getTime() === today.getTime() && (!this.availableTimeIntervals || this.availableTimeIntervals.length === 0)) {
                    this.fpDate.setDate(tomorrowStr(), true); // триггерит onChange -> updateAvailableTimeIntervals
                }
            };

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
                    if (sel.length) {
                        this.updateAvailableTimeIntervals();
                        // после обновления — автоподбор времени
                        this.$nextTick(() => {
                            moveToTomorrowIfNoIntervalsToday();
                            autoPickTimeIfNeeded();
                        });
                    }
                },
            });

            // Устанавливаем начальное состояние при загрузке
            this.updateFieldsState();

            // Обновляем доступные интервалы после инициализации
            this.$nextTick(() => {
                this.updateAvailableTimeIntervals();
                this.$nextTick(() => {
                    moveToTomorrowIfNoIntervalsToday();
                    autoPickTimeIfNeeded();
                });
            });

            this.$watch('mode', () => {
                this.updateFieldsState();

                // если включили fixed — сразу автоставим время
                if (this.mode === 'fixed') {
                    this.$nextTick(() => {
                        this.updateAvailableTimeIntervals();
                        this.$nextTick(() => {
                            moveToTomorrowIfNoIntervalsToday();
                            autoPickTimeIfNeeded();
                        });
                    });
                }

                // Сохраняем изменение в сессию
                let event = new Event('change');
                let form = document.querySelector('[data-checkout-form]');
                if (form) form.dispatchEvent(event);
            });

            // Отслеживаем изменение selectedTime для сохранения в сессию
            this.$watch('selectedTime', () => {
                if (this.mode === 'fixed') {
                    this.saveFormData();
                }
            });

            // если пересчитались интервалы — попробуем поставить дефолт
            this.$watch('availableTimeIntervals', () => {
                if (this.mode === 'fixed') {
                    this.$nextTick(() => autoPickTimeIfNeeded());
                }
            });
        },

        updateAvailableTimeIntervals() {
            if (!this.$refs.date || !this.fpDate) {
                this.availableTimeIntervals = this.allTimeIntervals || [];
                return;
            }

            const selectedDate = this.fpDate.selectedDates[0];
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Сохраняем текущее выбранное время перед фильтрацией
            const currentSelected = this.selectedTime;

            // Если выбрана не сегодняшняя дата, показываем все интервалы
            if (!selectedDate || selectedDate.getTime() !== today.getTime()) {
                this.availableTimeIntervals = this.allTimeIntervals || [];
                if (currentSelected && this.availableTimeIntervals.includes(currentSelected)) {
                    this.selectedTime = currentSelected;
                }
                return;
            }

            // Если выбрана сегодняшняя дата, фильтруем прошедшие интервалы
            const now = new Date();
            const nowMinutes = now.getHours() * 60 + now.getMinutes();
            const minMinutes = nowMinutes + (this.leadMinutes || 0);

            this.availableTimeIntervals = (this.allTimeIntervals || []).filter(interval => {
                const match = interval.match(/^(\d{2}):(\d{2})-/);
                if (!match) return true;

                const intervalStartMinutes = parseInt(match[1]) * 60 + parseInt(match[2]);
                return intervalStartMinutes >= minMinutes;
            });

            // если выбранное время ещё доступно — оставляем
            if (currentSelected && this.availableTimeIntervals.includes(currentSelected)) {
                this.selectedTime = currentSelected;
            } else {
                // не сбрасываем принудительно — автоподбор сделает своё
                // this.selectedTime = '';
            }
        },

        saveFormData() {
            let form = document.querySelector('[data-checkout-form]');
            if (form) {
                let event = new Event('change');
                form.dispatchEvent(event);
            }
        },

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

            if (!fixed) {
                if (this.fpDate) this.fpDate.clear();
                if (timeSelect) {
                    timeSelect.value = '';
                    this.selectedTime = '';
                }
            } else {
                // Если переключились на fixed, устанавливаем дату по умолчанию (сегодня)
                if (this.fpDate && !this.$refs.date.value) {
                    const d = new Date();
                    const t = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                    this.fpDate.setDate(t, true);
                }

                this.$nextTick(() => {
                    this.updateAvailableTimeIntervals();
                    this.$nextTick(() => {
                        // если на сегодня уже нет интервалов — fpDate сам поставит завтра (через onChange)
                        const sel = this.fpDate?.selectedDates?.[0];
                        if (sel) {
                            const today = new Date(); today.setHours(0,0,0,0);
                            const sel0 = new Date(sel); sel0.setHours(0,0,0,0);
                            if (sel0.getTime() === today.getTime() && this.availableTimeIntervals.length === 0) {
                                const d2 = new Date(); d2.setDate(d2.getDate() + 1);
                                const tom = `${d2.getFullYear()}-${String(d2.getMonth() + 1).padStart(2,'0')}-${String(d2.getDate()).padStart(2,'0')}`;
                                this.fpDate.setDate(tom, true);
                                return; // onChange дальше сделает автоподбор
                            }
                        }

                        // иначе подставим ближайшее
                        if (!this.selectedTime) {
                            if (this.savedTime && this.availableTimeIntervals.includes(this.savedTime)) {
                                this.selectedTime = this.savedTime;
                            } else if (this.availableTimeIntervals.length) {
                                this.selectedTime = this.availableTimeIntervals[0];
                            }
                        }
                    });
                });
            }
        }
    };
}


/* ===== NEW: Alpine component для "Доступные акции" ===== */
window.availablePromosComponent = function (initialSelected) { // 👈 NEW
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
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .getAttribute('content'),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ promo: this.selected }),
            })
                .then(r => r.json())
                .then(data => {
                    // гость – требуется авторизация
                    if (data.requires_auth) {
                        // откатываем выбор на "Без акции"
                        this.selected = 'none';
                        const noneRadio = document.querySelector('input[name="promo_radio"][value="none"]');
                        if (noneRadio) {
                            noneRadio.checked = true;
                        }

                        // показываем маленький модал "Потрібна авторизація"
                        window.location.href = '/auth';
                        return;
                        window.dispatchEvent(new CustomEvent('show-auth-modal', {
                            detail: {
                                message: data.message || 'Щоб застосувати акцію, увійдіть або зареєструйтесь.',
                            },
                        }));
                        return;
                    }

                    if (!data.ok) {
                        return;
                    }

                    // обновляем "Скидка"
                    const discountEl = document.querySelector('[data-checkout-discount]');
                    if (discountEl) {
                        discountEl.textContent = data.discount_formatted;
                    }

                    // обновляем "Всего" (большие цифры)
                    const totalUahEl = document.querySelector('[data-checkout-total-uah]');
                    const totalKopEl = document.querySelector('[data-checkout-total-kop]');

                    if (totalUahEl) {
                        totalUahEl.textContent = data.total_uah_formatted ?? data.total_uah;
                    }
                    if (totalKopEl) {
                        totalKopEl.textContent = data.total_kop;
                    }
                })
                .catch(() => {});
        },
    };
};

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
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute('content'),
                    },
                    body: JSON.stringify({ coupon: this.coupon }),
                });

                const res = await response.json();

                if (!res.ok) {
                    this.error   = res.mess;
                    this.applied = false;
                    this.discount = 0;

                    if (window.checkoutTotals && typeof window.checkoutTotals.resetPromo === 'function') {
                        window.checkoutTotals.resetPromo();
                    }

                    return;
                }

                this.applied  = true;
                this.discount = res.discount;

                if (window.checkoutTotals && typeof window.checkoutTotals.applyPromo === 'function') {
                    window.checkoutTotals.applyPromo(this.discount);
                }

            } catch (e) {
                this.error = 'Ошибка соединения';
            }
        },
    };
};

window.checkoutTotals = {
    subtotal: 0,
    baseDiscount: 0,
    promoDiscount: 0,
    bonus: 0,

    init() {
        const subEl   = document.querySelector('[data-checkout-subtotal]');
        const discEl  = document.querySelector('[data-checkout-discount]');
        const bonusEl = document.querySelector('[data-checkout-bonus]');

        if (subEl)   this.subtotal     = this.parseMoney(subEl.textContent);
        if (discEl)  this.baseDiscount = this.parseMoney(discEl.textContent);
        if (bonusEl) this.bonus        = this.parseMoney(bonusEl.textContent);

        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },

    applyPromo(discount) {
        this.promoDiscount = discount || 0;
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },

    resetPromo() {
        this.promoDiscount = 0;
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },

    parseMoney(text) {
        if (!text) return 0;
        const cleaned = text.replace(/[^\d,.\-]/g, '').replace(/\s+/g, '');
        const normalized = cleaned.replace(',', '.');
        const value = parseFloat(normalized);
        return isNaN(value) ? 0 : value;
    },

    formatMoney(value) {
        return new Intl.NumberFormat('uk-UA', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    },

    updateDiscountDisplay() {
        const discEl = document.querySelector('[data-checkout-discount]');
        if (!discEl) return;

        const totalDiscount = this.baseDiscount + this.promoDiscount;
        discEl.textContent = this.formatMoney(totalDiscount) + ' ';
    },

    updateTotalDisplay() {
        const wrap = document.querySelector('[data-checkout-total-wrapper]');
        const uahEl = wrap?.querySelector('[data-checkout-total-uah]');
        const kopEl = wrap?.querySelector('[data-checkout-total-kop]');

        const total =
            this.subtotal - this.baseDiscount - this.promoDiscount - this.bonus;

        const safeTotal = Math.max(total, 0);
        const uah = Math.floor(safeTotal);
        const kop = Math.round((safeTotal - uah) * 100);

        if (uahEl) {
            uahEl.textContent = new Intl.NumberFormat('uk-UA', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }).format(uah);
        }
        if (kopEl) {
            kopEl.textContent = String(kop).padStart(2, '0');
        }
    },
};


/* ===== Register with Alpine once ===== */
function registerCheckoutAlpine() {
    Alpine.data('deliveryBlock', deliveryBlock);
    Alpine.data('tooltip', (text) => tooltip(text));
    // компонент акций используется как x-data="availablePromosComponent('...')"  👈 NEW
}

if (window.Alpine) {
    registerCheckoutAlpine();
} else {
    window.addEventListener('alpine:init', registerCheckoutAlpine);
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.checkoutTotals && typeof window.checkoutTotals.init === 'function') {
        window.checkoutTotals.init();
    }

    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    const isGuest =
        window.isGuestCheckout === true || window.isGuestCheckout === 'true';

    if (!isGuest) {
        // авторизованному пользователю ничего не блокируем
        return;
    }

    form.addEventListener('submit', (e) => {
        // ВАЖНО:
        // если форма не проходит HTML5-валидацию (required и т.п.),
        // этот обработчик вообще не вызовется — браузер сам подсветит поля.
        e.preventDefault();
        const nameInput  = form.querySelector('input[name="contact_name"]');
        const phoneInput = form.querySelector('input[name="contact_phone"]');

        const name  = nameInput  ? nameInput.value.trim()  : '';
        const phone = phoneInput ? phoneInput.value.trim() : '';

        // Сохраняем URL checkout в сессии перед показом модалки авторизации
        const checkoutUrl = window.location.href;
        fetch('/auth/save-checkout-url', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ url: checkoutUrl }),
        }).catch(() => {}); // Игнорируем ошибки, это не критично

        window.dispatchEvent(
            new CustomEvent('show-auth-modal', {
                detail: {
                    message:
                        'Щоб оформити замовлення, увійдіть або зареєструйтесь.',
                },
            }),
        );
    });
});


