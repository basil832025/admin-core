// resources/js/alpine/cart-actions.js

/** Регистрирует общий cart-store (бейдж в хедере и т.п.) */
export function registerCartStore(Alpine, { infoUrl = '/cart/info', initQty = 0, initTotal = 0 } = {}) {
    // создаём store один раз
    if (!Alpine.store('cart')) {
        Alpine.store('cart', {
            qty: Number(initQty || 0),
            total: Number(initTotal || 0),
            setQty(q)   { this.qty   = Number(q || 0); },
            setTotal(s) { this.total = Number(s || 0); },
        });
    }

    // первичная загрузка состояния - используем глобальный кеш, если доступен
    const loadCartData = async () => {
        try {
            let data;
            if (window.__CART_CACHE__) {
                // Используем кеш, чтобы избежать дублирования запросов
                data = await window.__CART_CACHE__.get();
            } else {
                // Fallback на прямой запрос, если кеш не инициализирован
                const res = await fetch(infoUrl, { headers: { 'Accept': 'application/json' } });
                data = await res.json();
            }
            Alpine.store('cart').setQty(Number(data.qty ?? 0));
            Alpine.store('cart').setTotal(Number(data.total_price ?? data.total ?? 0));
        } catch (_) {
            // Игнорируем ошибки
        }
    };
    loadCartData();

    // любое обновление корзины (из patchDom) — обновляем бейдж
    window.addEventListener('cart-updated', (e) => {
        const d = e.detail || {};
        if ('qty' in d)        Alpine.store('cart').setQty(d.qty);
        if ('total_price' in d || 'total' in d) {
            Alpine.store('cart').setTotal(Number(d.total_price ?? d.total ?? 0));
        }
    });
}

async function resolveCsrfToken() {
    if (typeof window.ensureCsrfToken === 'function') {
        return await window.ensureCsrfToken();
    }

    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** Локальный компонент для списков/сайдбаров корзины (твоя версия, с правками удаления) */
export default function registerCartActions(Alpine) {
    Alpine.data('cartActions', (addUrl, removeUrl) => ({
        addUrl,
        removeUrl,

        money(n) {
            return Number(n || 0).toLocaleString('uk-UA') + ' грн';
        },

        patchDom(data, payload = {}) {
            try {
                // A) пришёл item (inc/dec/set)
                if (data && data.item) {
                    const it  = data.item;
                    const row = document.querySelector(`[data-cart-item="${it.product_id}"]`);
                    if (row) {
                        if (data.removed || Number(it.qty) <= 0) {
                            row.remove();
                        } else {
                            const input = row.querySelector('[data-cart-qty-input]');
                            if (input) input.value = String(it.qty ?? '');
                            const qtyEl = row.querySelector('[data-cart-qty]');
                            if (qtyEl) qtyEl.textContent = String(it.qty ?? '');
                            const lineEl = row.querySelector('[data-cart-line-total]');
                            if (lineEl) lineEl.textContent = Number(it.line_total || 0).toLocaleString('uk-UA');
                            const oldLineEl = row.querySelector('[data-cart-line-old-total]');
                            if (oldLineEl) {
                                if (it.old_line_total && Number(it.old_line_total) > Number(it.line_total || 0)) {
                                    oldLineEl.textContent = Number(it.old_line_total).toLocaleString('uk-UA') + ' грн';
                                    oldLineEl.classList.remove('hidden');
                                } else {
                                    oldLineEl.textContent = '';
                                    oldLineEl.classList.add('hidden');
                                }
                            }
                        }
                    }
                }
                // B) удаления без item (del)
                else if (data?.removed) {
                    const id = data.id ?? payload.product_id ?? payload.id;
                    if (id != null) {
                        const row = document.querySelector(`[data-cart-item="${id}"]`);
                        if (row) row.remove();
                    }
                }

                // итоги
                const qty   = Number(data?.qty ?? data?.total_qty ?? 0);
                const total = Number(data?.total_price ?? data?.total ?? 0);

                // обновим store (бейдж)
                if (window.Alpine?.store('cart')) {
                    Alpine.store('cart').setQty(qty);
                    Alpine.store('cart').setTotal(total);
                }

                // итог в этом виджете
                const totalEl = document.querySelector('[data-cart-total]');
                if (totalEl) totalEl.textContent = this.money(total);

                // пустое состояние
                const listEl  = document.querySelector('[data-cart-list]');
                const emptyEl = document.querySelector('[data-cart-empty]');
                if (listEl && emptyEl) {
                    if (qty <= 0) {
                        listEl.classList.add('hidden');
                        emptyEl.classList.remove('hidden');
                    } else {
                        listEl.classList.remove('hidden');
                        emptyEl.classList.add('hidden');
                    }
                }

                // уведомим весь сайт
                window.dispatchEvent(new CustomEvent('cart-updated', { detail: data }));
            } catch (e) {
                console.error('cart.patchDom error:', e, data);
            }
        },

        normalizeQty(v) {
            let n = parseInt(String(v ?? '').replace(/\D+/g, ''), 10);
            if (isNaN(n)) n = 0;
            if (n < 0) n = 0;
            if (n > 9999) n = 9999;
            return n;
        },
        // 🔎 помощник — находим инпут по товару
        findQtyInput(id) {
            return document.querySelector(`[data-cart-item="${id}"] [data-cart-qty-input]`);
        },
        // При ручном вводе разрешаем пустое значение, валидация только при blur
        onQtyInput(id, el) {
            // Разрешаем пустое значение во время ввода
            const value = el.value.trim();
            if (value === '') {
                // Поле пустое - не отправляем запрос, просто разрешаем пустое значение
                return;
            }
            
            // Если есть значение, нормализуем его, но не форсируем минимум 1
            let qty = this.normalizeQty(value);
            // Если значение 0 или отрицательное, оставляем как есть (пользователь может стереть)
            if (qty === 0) {
                // Значение 0 - не отправляем запрос, позволяем пользователю продолжить ввод
                return;
            }
            
            // Если значение валидное и > 0, обновляем отображение и отправляем
            el.value = String(qty);
            this.set(id, qty);
        },
        onQtyBlur(id, el) {
            // При потере фокуса валидируем и устанавливаем минимум 1
            let qty = this.normalizeQty(el.value);
            if (qty < 1) qty = 1;
            el.value = String(qty);
            this.set(id, qty);
        },

        // ❗ сет всегда >= 1
        set(id, qty) {
            qty = this.normalizeQty(qty);
            if (qty < 1) qty = 1;
            return this._send(this.addUrl, { product_id: id, qty, set: true });
        },
        inc(id)      { return this._send(this.addUrl, { product_id: id, qty:  1 }); },
        // ❗ минус не даём уйти ниже 1
        dec(id) {
            const input = this.findQtyInput(id);
            const cur = this.normalizeQty(input?.value);
            if (cur <= 1) {
                if (input) input.value = '1';
                return; // ничего не отправляем — уже минимум
            }
            return this._send(this.addUrl, { product_id: id, qty: -1 });
        },
        del(id)      { return this._send(this.removeUrl, { product_id: id, all: true, id }); },

        async _send(url, payload) {
            let data = {};
            try {
                const csrf = await resolveCsrfToken();
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const text = await res.text();
                try { data = JSON.parse(text); } catch { data = {}; }
            } catch (e) {
                console.error('cart._send error:', e);
            }
            this.patchDom(data, payload);
            return data;
        },
    }));

    /** Глобальный помощник для кнопок «Добавить в корзину» вне x-data */
    if (!window.CartAPI) window.CartAPI = {};
    window.CartAPI.add = async (addUrl, payload = {}) => {
        try {
            const csrf = await resolveCsrfToken();
            const res = await fetch(addUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));

            // обновим глобальный бейдж через событие
            const qty   = Number(data?.qty ?? data?.total_qty ?? 0);
            const total = Number(data?.total_price ?? data?.total ?? 0);
            if (window.Alpine?.store('cart')) {
                Alpine.store('cart').setQty(qty);
                Alpine.store('cart').setTotal(total);
            }
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: data }));
            return data;
        } catch (e) {
            console.error('CartAPI.add error', e);
            return {};
        }
    };
}
