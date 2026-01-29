/* ===== Alpine components for checkout page ===== */
const Money = {
    parse(text) {
        text = (text || '').toString().replace(/[^\d,.\-]/g, '').replace(/\s+/g, '');
        text = text.replace(',', '.');
        const n = parseFloat(text);
        return isNaN(n) ? 0 : n;
    },
    format(n) {
        n = Number(n || 0);
        try {
            return new Intl.NumberFormat('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
        } catch (e) {
            return (Math.round(n * 100) / 100).toFixed(2).replace('.', ',');
        }
    }
};

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
    money: {
        parse(text) {
            text = (text || '').toString();
            text = text.replace(/[^\d,.\-]/g, '');

            const lastComma = text.lastIndexOf(',');
            const lastDot   = text.lastIndexOf('.');
            const decPos = Math.max(lastComma, lastDot);

            if (decPos !== -1) {
                const intPart  = text.slice(0, decPos).replace(/[.,]/g, '');
                const fracPart = text.slice(decPos + 1).replace(/[^\d]/g, '');
                text = intPart + '.' + fracPart;
            } else {
                text = text.replace(/[^\d\-]/g, '');
            }

            const n = parseFloat(text);
            return isNaN(n) ? 0 : n;
        },
        format(value) {
            return new Intl.NumberFormat('uk-UA', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(Number(value || 0));
        },
    },
    subtotal: 0,
    baseDiscount: 0,
    promoDiscount: 0,
    bonus: 0,
    shipping: 0,

    init() {
        this.refreshFromDom();
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
     //   if (window.checkoutDeliveryRecalc) window.checkoutDeliveryRecalc();

        this.updateShippingDisplay();
    },

    refreshFromDom() {
        const subEl   = document.querySelector('[data-checkout-subtotal]');
        const discEl  = document.querySelector('[data-checkout-discount]');
        const bonusEl = document.querySelector('[data-checkout-bonus]');

        if (subEl)   this.subtotal     = this.money.parse(subEl.textContent);
        if (discEl)  this.baseDiscount = this.money.parse(discEl.textContent);
        if (bonusEl) this.bonus        = this.money.parse(bonusEl.textContent);
    },

    setShipping(value) {
        this.shipping = Number(value || 0);
        this.updateShippingDisplay();
        this.updateTotalDisplay();
    },
    render() {
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },
    applyPromo(discount) {
        this.promoDiscount = Number(discount || 0);
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },

    resetPromo() {
        this.promoDiscount = 0;
        this.updateDiscountDisplay();
        this.updateTotalDisplay();
    },

    parseMoney(text) {
        text = (text || '').toString();
        text = text.replace(/[^\d,.\-]/g, '');

        const lastComma = text.lastIndexOf(',');
        const lastDot   = text.lastIndexOf('.');
        const decPos = Math.max(lastComma, lastDot);

        if (decPos !== -1) {
            const intPart  = text.slice(0, decPos).replace(/[.,]/g, '');
            const fracPart = text.slice(decPos + 1).replace(/[^\d]/g, '');
            text = intPart + '.' + fracPart;
        } else {
            text = text.replace(/[^\d\-]/g, '');
        }

        const n = parseFloat(text);
        return isNaN(n) ? 0 : n;
    },

    formatMoney(value) {
        return new Intl.NumberFormat('uk-UA', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    },

    updateDiscountDisplay() {
        const discEl = document.querySelector('[data-checkout-discount]');
        if (!discEl) return;

        const totalDiscount = this.baseDiscount + this.promoDiscount;
        discEl.textContent = this.money.format(totalDiscount);
    },

    updateShippingDisplay() {
        const shipEl = document.querySelector('[data-checkout-shipping]');
        if (shipEl) shipEl.textContent = this.money.format(this.shipping);
    },

    updateTotalDisplay() {
        const total =
            Math.max(
                this.subtotal - this.baseDiscount - this.promoDiscount - this.bonus,
                0
            ) + this.shipping;

        const uah = Math.floor(total);
        let kop = Math.round((total - uah) * 100);
        let uahFixed = uah;
        if (kop === 100) { uahFixed += 1; kop = 0; }

        const uahEl = document.querySelector('[data-checkout-total-uah]');
        const kopEl = document.querySelector('[data-checkout-total-kop]');

        if (uahEl) {
            uahEl.textContent = new Intl.NumberFormat('uk-UA', {
                maximumFractionDigits: 0
            }).format(uahFixed);
        }
        if (kopEl) kopEl.textContent = String(kop).padStart(2, '0');
    },
};
window.checkoutDelivery = (function () {

    function waitForGoogle(cb) {
        if (typeof google !== 'undefined' && google.maps && google.maps.geometry && google.maps.geometry.poly) {
            cb();
            return;
        }
        setTimeout(() => waitForGoogle(cb), 200);
    }
/*
    function ensureDeliveryPolygonsReady(cb) {
        waitForGoogle(() => {
            if (!window.deliveryAreas) return cb(false);
            if (window.__deliveryPolygonsReady) return cb(true);

            function getZoneParams(zoneKey) {
                const zoneGroup = (zoneKey || '').split('_')[0];
                if (window.DELIVERY_ZONES && window.DELIVERY_ZONES[zoneGroup]) {
                    const z = window.DELIVERY_ZONES[zoneGroup];
                    return {
                        price: parseFloat(z.delivery_price) || 0,
                        free:  parseFloat(z.free_delivery_from) || 0,
                        color: z.color || (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].color) || '#000000',
                    };
                }
                return {
                    price: (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].price) || 0,
                    free:  (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].free) || 0,
                    color: (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].color) || '#000000',
                };
            }

            for (const key in window.deliveryAreas) {
                if (!Object.prototype.hasOwnProperty.call(window.deliveryAreas, key)) continue;
                const area = window.deliveryAreas[key];
                if (!area) continue;

                if (!area.polygon) {
                    area.polygon = new google.maps.Polygon({
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
        });
    }
*/
    function calcShippingByCoords(lat, lng, cb) {
        ensureDeliveryPolygonsReady((ok) => {
            if (!ok) return cb(0);

            const latN = parseFloat(lat);
            const lngN = parseFloat(lng);
            if (isNaN(latN) || isNaN(lngN)) return cb(0);

            if (typeof window.resolveAreaByLatLng !== 'function') return cb(0);

            const latLng = new google.maps.LatLng(latN, lngN);
            const area = window.resolveAreaByLatLng(latLng);

            if (!area) return cb(0);

            // важно: totals берём из checkoutTotals (а не из DOM)
            window.checkoutTotals.refreshFromDom();

            const base =
                Math.max(
                    window.checkoutTotals.subtotal
                    - window.checkoutTotals.baseDiscount
                    - window.checkoutTotals.promoDiscount
                    - window.checkoutTotals.bonus,
                    0
                );

            const freeFrom = parseFloat(area.free) || 0;
            const price    = parseFloat(area.price) || 0;

            const shipping = (base > 0 && freeFrom > 0 && base >= freeFrom) ? 0 : price;
            cb(shipping);
        });
    }

    function getSelectedSavedAddressCoords() {
        const r = document.querySelector('[name="selected_address_id"]:checked');
        if (!r) return null;
        const lat = parseFloat(r.dataset.lat);
        const lng = parseFloat(r.dataset.lng);
        if (isNaN(lat) || isNaN(lng)) return null;
        return { lat, lng };
    }

    function bindSavedAddresses() {
        document.addEventListener('change', function(e){
            const t = e.target;
            if (!t || t.name !== 'selected_address_id') return;

            const c = getSelectedSavedAddressCoords();
            if (!c) return;

            calcShippingByCoords(c.lat, c.lng, (shipping) => {
                window.checkoutTotals.setShipping(shipping);
            });
        });

        // первичный расчёт
        const c0 = getSelectedSavedAddressCoords();
        if (c0) {
            calcShippingByCoords(c0.lat, c0.lng, (shipping) => {
                window.checkoutTotals.setShipping(shipping);
            });
        }
    }

    return {
        bindSavedAddresses,
        calcShippingByCoords,
    };
})();



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
window.checkoutDeliveryRecalc = function () {
    const r = document.querySelector('[name="selected_address_id"]:checked');
    if (!r || !window.checkoutDelivery) return;

    const lat = parseFloat(r.dataset.lat);
    const lng = parseFloat(r.dataset.lng);
    if (isNaN(lat) || isNaN(lng)) return;

    window.checkoutDelivery.calcShippingByCoords(lat, lng, (shipping) => {
        window.checkoutTotals.setShipping(shipping);
    });
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.checkoutTotals && typeof window.checkoutTotals.init === 'function') {
        window.checkoutTotals.init();
    }
    if (window.checkoutDelivery) window.checkoutDelivery.bindSavedAddresses();
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


// resources/js/checkout.js

// ---------- helpers ----------
function debounce(fn, wait) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}



// ---------- TOTALS (одна точка правды) ----------
window.checkoutTotals = window.checkoutTotals || {
    promoDiscount: 0,
    shipping: 0,

    // читаем актуальные цифры из DOM каждый раз (надёжно)
    readBase() {
        const sub = Money.parse(document.querySelector('[data-checkout-subtotal]')?.textContent);
        const disc = Money.parse(document.querySelector('[data-checkout-discount]')?.textContent);
        const bonus = Money.parse(document.querySelector('[data-checkout-bonus]')?.textContent);

        return { sub, disc, bonus };
    },

    setShipping(v) {
        this.shipping = Number(v || 0);
        this.render();
    },

    setPromoDiscount(v) {
        this.promoDiscount = Number(v || 0);
        this.render();
    },

    render() {
        // доставка строкой
        const shipEl = document.querySelector('[data-checkout-shipping]');
        if (shipEl) shipEl.textContent = Money.format(this.shipping);

        // hidden inputs (если есть)
        const shipInput = document.querySelector('[data-shipping-price-input]');
        if (shipInput) shipInput.value = String(this.shipping || 0);

        // итог
        const { sub, disc, bonus } = this.readBase();
        const total = Math.max(sub - disc - bonus - (this.promoDiscount || 0), 0) + (this.shipping || 0);

        const uah = Math.floor(total);
        let kop = Math.round((total - uah) * 100);
        let u = uah;
        if (kop === 100) { u += 1; kop = 0; }

        const uahEl = document.querySelector('[data-checkout-total-uah]');
        if (uahEl) {
            uahEl.textContent = new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(u);
        }

        const kopEl = document.querySelector('[data-checkout-total-kop]');
        if (kopEl) {
            kopEl.textContent = String(kop).padStart(2, '0');
        }


    }
};

// ---------- mobile blocks order ----------
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

// ---------- save form data ----------
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

            shipping_method: form.querySelector('[name="shipping_method"]')?.value || '',
            selected_address_id: form.querySelector('[name="selected_address_id"]:checked')?.value || '',
            use_new_address: form.querySelector('[name="use_new_address"]')?.value || '0',

            delivery_mode: form.querySelector('[name="delivery_mode"]')?.value || '',
            delivery_date: form.querySelector('[name="delivery_date"]')?.value || '',
            delivery_time: form.querySelector('[name="delivery_time"]')?.value || '',

            payment_method: form.querySelector('[name="payment_method"]:checked')?.value || '',

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

// ---------- Google loader ----------
function loadGoogleMapsOnce(cb) {
    if (window.google?.maps?.places && window.google?.maps?.geometry) return cb(true);

    window.__googleMapsLoading = window.__googleMapsLoading ?? false;
    window.__googleMapsLoaded = window.__googleMapsLoaded ?? false;

    if (window.__googleMapsLoaded) return cb(true);

    // уже грузится
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

// ---------- delivery calc (ЕДИНАЯ) ----------
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
/*
function ensureDeliveryPolygonsReady(cb) {
    if (!window.google?.maps?.Polygon || !window.google?.maps?.geometry?.poly) {
        return setTimeout(() => ensureDeliveryPolygonsReady(cb), 200);
    }
    if (!window.deliveryAreas) return cb(false);
    if (window.__deliveryPolygonsReady) return cb(true);

    for (const key in window.deliveryAreas) {
        const area = window.deliveryAreas[key];
        if (!area) continue;

        if (!area.polygon) {
            area.polygon = new google.maps.Polygon({ path: area.area, geodesic: true, map: null });
        }

        // синхроним price/free из DELIVERY_ZONES по группе
        const group = (key || '').split('_')[0];
        const z = window.DELIVERY_ZONES?.[group];
        if (z) {
            area.price = parseFloat(z.delivery_price) || 0;
            area.free  = parseFloat(z.free_delivery_from) || 0;
            if (z.color) area.color = z.color;
        }
    }

    window.__deliveryPolygonsReady = true;
    cb(true);
}
*/
window.ensureDeliveryPolygonsReady =
    window.ensureDeliveryPolygonsReady ||
    function ensureDeliveryPolygonsReady(cb) {
        const g = window.google;

        // 1) ждём google полностью
        if (!g || !g.maps || !g.maps.Polygon || !g.maps.geometry || !g.maps.geometry.poly) {
            return setTimeout(() => window.ensureDeliveryPolygonsReady(cb), 200);
        }

        // 2) ждём deliveryAreas из map-cart.js
        if (!window.deliveryAreas) {
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

function calcShippingByCoords(lat, lng) {
    return new Promise((resolve) => {
        window.ensureDeliveryPolygonsReady((ok) => {
            if (!ok) return resolve({ shipping: 0, zone: '' });

            const latN = parseFloat(lat), lngN = parseFloat(lng);
            if (isNaN(latN) || isNaN(lngN)) return resolve({ shipping: 0, zone: '' });
            const g = window.google;
            const area = window.resolveAreaByLatLng?.(new g.maps.LatLng(latN, lngN));
            if (!area) return resolve({ shipping: 0, zone: '' });

            const rawKey = inferAreaKey(area);     // например Brown_2
            const group = rawKey ? rawKey.split('_')[0] : null; // Brown
            const z = group ? window.DELIVERY_ZONES?.[group] : null;
            if (!z) return resolve({ shipping: 0, zone: '' });

            // базу считаем из DOM (с учётом скидок/бонусов)
            const itemsTotal = Money.parse(document.querySelector('[data-checkout-subtotal]')?.textContent);
            const discount   = Money.parse(document.querySelector('[data-checkout-discount]')?.textContent);
            const bonus      = Money.parse(document.querySelector('[data-checkout-bonus]')?.textContent);
            const promo      = window.checkoutTotals?.promoDiscount || 0;

            const base = Math.max(itemsTotal - discount - bonus - promo, 0);

            const freeFrom = parseFloat(z.free_delivery_from) || 0;
            const price = parseFloat(z.delivery_price) || 0;

            const shipping = (freeFrom > 0 && base >= freeFrom) ? 0 : price;

            resolve({ shipping, zone: z.name || group });
        });
    });
}

function bindDeliveryRecalc() {
    // сохранённые адреса (radio)
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (t && t.matches('input[name="selected_address_id"]')) {
            const lat = t.dataset.lat;
            const lng = t.dataset.lng;
            calcShippingByCoords(lat, lng).then(({ shipping, zone }) => {
                const z = document.querySelector('[data-delivery-zone-input]');
                if (z) z.value = zone || '';
                window.checkoutTotals.setShipping(shipping);
            });
        }
    });

    // стартовый пересчёт
    const checked = document.querySelector('input[name="selected_address_id"]:checked');
    if (checked) {
        calcShippingByCoords(checked.dataset.lat, checked.dataset.lng).then(({ shipping, zone }) => {
            const z = document.querySelector('[data-delivery-zone-input]');
            if (z) z.value = zone || '';
            window.checkoutTotals.setShipping(shipping);
        });
    } else {
        window.checkoutTotals.render();
    }

    // новый адрес — если у тебя есть hidden lat/lng
    const latEl = document.getElementById('checkout-address-lat');
    const lngEl = document.getElementById('checkout-address-lng');

    const triggerNew = () => {
        const lat = latEl?.value;
        const lng = lngEl?.value;
        if (!lat || !lng) return;
        calcShippingByCoords(lat, lng).then(({ shipping, zone }) => {
            document.querySelector('[data-delivery-zone-input]')?.setAttribute('value', zone || '');
            window.checkoutTotals.setShipping(shipping);
        });
    };

    if (latEl && lngEl) {
        latEl.addEventListener('input', triggerNew);
        lngEl.addEventListener('input', triggerNew);
        latEl.addEventListener('change', triggerNew);
        lngEl.addEventListener('change', triggerNew);
    }
}

// ---------- initAddressAutocomplete ----------
function initCheckoutAutocomplete() {
    if (typeof window.initAddressAutocomplete === 'undefined') return;

    window.initAddressAutocomplete({
        streetInputId: 'checkout-address-street',
        houseInputId: 'checkout-address-house',
        cityInputSelector: '#checkout-address-city',
        kyivOnly: true,
        filterByDeliveryZone: true,
        googleMapsKey: window.CHECKOUT_CONFIG?.googleMapsKey,
    });
}

// ---------- resetNewAddress ----------
window.resetNewAddress = function(btn){
    const form = btn.closest('form');
    if (!form) return;

    ['addr[street]','addr[house]','addr[apartment]','addr[porch]','addr[intercom]','addr[floor]','addr[comment]'].forEach((name) => {
        const el = form.querySelector('[name="'+name+'"]');
        if (el) { el.value=''; el.dispatchEvent(new Event('input',{bubbles:true})); }
    });

    const priv = form.querySelector('[name="addr[is_private_house]"]');
    if (priv) priv.checked = false;

    form.querySelectorAll('.tp-error').forEach(p => p.classList.add('hidden'));
    form.querySelectorAll('.tp-float-wrap.is-invalid').forEach(w => w.classList.remove('is-invalid'));
};

// ---------- boot ----------
document.addEventListener('DOMContentLoaded', () => {
    applyCheckoutLayout();
    window.addEventListener('resize', applyCheckoutLayout);

    bindCheckoutAutosave();

    // totals initial render (чтобы не было 0)
    window.checkoutTotals.render();

    loadGoogleMapsOnce((ok) => {
        // автокомплит и доставка завязаны на google/maps + map-cart.js
        if (ok) initCheckoutAutocomplete();
        bindDeliveryRecalc();
    });
});
function initCheckoutRequiredValidation() {
    const form = document.querySelector('[data-checkout-form]');
    if (!form) return;

    function getFieldValue(form, name) {
        const els = form.querySelectorAll('[name="' + CSS.escape(name) + '"]');
        if (!els.length) return '';

        const first = els[0];
        if (first.type === 'radio') {
            const checked = form.querySelector('[name="' + CSS.escape(name) + '"]:checked');
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
        const err = form.querySelector('[data-error-for="'+CSS.escape(name)+'"]');
        if (err) err.classList.remove('hidden');

        const wrap = form.querySelector('[data-field-wrap="'+CSS.escape(name)+'"] .tp-float-wrap');
        if (wrap) { wrap.classList.add('is-invalid'); return; }

        const el = form.querySelector('[name="'+CSS.escape(name)+'"]');
        if (el) el.classList.add('is-invalid');
    }

    function clearError(form, name) {
        const err = form.querySelector('[data-error-for="'+CSS.escape(name)+'"]');
        if (err) err.classList.add('hidden');

        const wrap = form.querySelector('[data-field-wrap="'+CSS.escape(name)+'"] .tp-float-wrap');
        if (wrap) { wrap.classList.remove('is-invalid'); return; }

        const el = form.querySelector('[name="'+CSS.escape(name)+'"]');
        if (el) el.classList.remove('is-invalid');
    }

    function focusField(form, name) {
        const el = form.querySelector('[name="'+CSS.escape(name)+'"]') || document.getElementById(name);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.focus(), 150);
    }

    function validateForm(form) {
        // очистка
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

    // ВАЖНО: capture=true — чтобы сработало раньше любых других submit-хендлеров (guest auth и т.п.)
    form.addEventListener('submit', function (e) {
        if (!validateForm(form)) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation?.();
        }
    }, true);

    // очистка ошибки при вводе
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

// запуск
document.addEventListener('DOMContentLoaded', () => {
    initCheckoutRequiredValidation();
});
function waitForGoogleMaps(cb) {
    const tick = () => {
        const g = window.google;

        const ok =
            window.__googleMapsLoaded === true &&
            g && g.maps &&
            g.maps.Polygon &&
            g.maps.LatLng &&
            g.maps.geometry &&
            g.maps.geometry.poly;

        if (ok) return cb();
        setTimeout(tick, 200);
    };
    tick();
}


function waitForDeliveryAreas(cb) {
    const tick = () => {
        if (window.deliveryAreas && typeof window.resolveAreaByLatLng === 'function') return cb();
        setTimeout(tick, 200);
    };
    tick();
}



function ensureDeliveryPolygonsReady() {
    if (!window.deliveryAreas) return false;
    if (window.__deliveryPolygonsReady) return true;

    function getZoneParams(zoneKey) {
        const zoneGroup = (zoneKey || '').split('_')[0];
        if (window.DELIVERY_ZONES && window.DELIVERY_ZONES[zoneGroup]) {
            const z = window.DELIVERY_ZONES[zoneGroup];
            return {
                price: parseFloat(z.delivery_price) || 0,
                free: parseFloat(z.free_delivery_from) || 0,
                color: z.color || (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].color) || '#000000',
            };
        }
        return {
            price: (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].price) || 0,
            free: (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].free) || 0,
            color: (window.deliveryAreas[zoneKey] && window.deliveryAreas[zoneKey].color) || '#000000',
        };
    }

    for (const key in window.deliveryAreas) {
        if (!Object.prototype.hasOwnProperty.call(window.deliveryAreas, key)) continue;
        const area = window.deliveryAreas[key];
        if (!area) continue;

        if (!area.polygon) {
            const g = window.google;
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
    return true;
}

function updateGrandTotal(shipping) {
    shipping = parseFloat(shipping) || 0;

    const itemsTotal = Money.parse(document.querySelector('[data-checkout-subtotal]')?.textContent);
    const discount   = Money.parse(document.querySelector('[data-checkout-discount]')?.textContent);
    const bonus      = Money.parse(document.querySelector('[data-checkout-bonus]')?.textContent);

    const total = Math.max(itemsTotal - discount - bonus, 0) + shipping;

    let uah = Math.floor(total);
    let kop = Math.round((total - uah) * 100);
    if (kop === 100) { uah += 1; kop = 0; }

    const uahEl = document.querySelector('[data-checkout-total-uah]');
    const kopEl = document.querySelector('[data-checkout-total-kop]');
    if (uahEl) uahEl.textContent = new Intl.NumberFormat('uk-UA', { maximumFractionDigits: 0 }).format(uah);
    if (kopEl) kopEl.textContent = String(kop).padStart(2, '0');
}

function recalcDeliveryByCoords(lat, lng) {
    if (!ensureDeliveryPolygonsReady()) return;

    const latN = parseFloat(lat), lngN = parseFloat(lng);
    if (isNaN(latN) || isNaN(lngN)) return;

    const latLng = new google.maps.LatLng(latN, lngN);
    const area = window.resolveAreaByLatLng(latLng);

    let shipping = 0;

    if (area) {
        const itemsTotal = Money.parse(document.querySelector('[data-checkout-subtotal]')?.textContent);
        const discount   = Money.parse(document.querySelector('[data-checkout-discount]')?.textContent);
        const bonus      = Money.parse(document.querySelector('[data-checkout-bonus]')?.textContent);
        const base = Math.max(itemsTotal - discount - bonus, 0);

        if (base > 0 && base < (parseFloat(area.free) || 0)) {
            shipping = parseFloat(area.price) || 0;
        }
    }

    const shipEl = document.querySelector('[data-checkout-shipping]');
    if (shipEl) shipEl.textContent = Money.format(shipping);

    updateGrandTotal(shipping);
}

function initDeliveryRecalcOnAddressSelect() {
    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement)) return;
        if (t.name !== 'selected_address_id') return;

        recalcDeliveryByCoords(t.dataset.lat, t.dataset.lng);
    });

    // стартовый пересчёт
    const checked = document.querySelector('input[name="selected_address_id"]:checked');
    if (checked) {
        recalcDeliveryByCoords(checked.dataset.lat, checked.dataset.lng);
    }
}

// запуск
document.addEventListener('DOMContentLoaded', () => {
    waitForGoogleMaps(() => {
        waitForDeliveryAreas(() => {
            initDeliveryRecalcOnAddressSelect();
        });
    });
});
