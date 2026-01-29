import './bootstrap';

import Alpine from 'alpinejs';
import Inputmask from 'inputmask';

import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import 'swiper/css/autoplay';

// общие модули
import './alpine/address-autocomplete';

import registerFavoriteButton from './components/favoriteButton.js';
import authModal from './alpine/auth-modal';
import scrollTabs from './alpine/scroll-tabs';
import registerCartActions, { registerCartStore } from './alpine/cart-actions';
import { registerFavoriteStore } from './alpine/favorite-store';

// Версия скрипта
window.APP_SCRIPT_VERSION = '2026-01-16-15:00';

// 1) СНАЧАЛА — делаем Alpine глобальным (до любых x-init)
window.Alpine = Alpine;

// 2) СНАЧАЛА — объявляем глобальные функции, которые дергаются из шаблонов (x-init / inline)
window.normalizePhone = function normalizePhone(val) {
    let d = String(val || '').replace(/\D/g, '');
    if (d.startsWith('0')) d = '38' + d;
    if (d.length === 9) d = '380' + d;
    if (!d.startsWith('380') && d.length >= 10) d = '380' + d.slice(-9);
    return d;
};

window.applyUaPhoneMask = function (el) {
    if (!el || el.__uaPhoneMasked) return;
    if (typeof el.value === 'undefined' && !('value' in el)) return;

    const PREFIX = '+38 0 ';
    const im = new Inputmask({
        mask: '+38 0 99 999 99 99',
        placeholder: ' ',
        showMaskOnHover: false,
        showMaskOnFocus: true,
        clearIncomplete: true,
    });

    im.mask(el);

    if (!el.value || !String(el.value || '').startsWith(PREFIX)) im.setValue(PREFIX);

    const ensurePrefix = () => {
        if (!el || !el.value) return;

        let digits = String(el.value || '').replace(/\D/g, '');
        if (digits.startsWith('380')) digits = digits.slice(3);
        else if (digits.startsWith('38')) digits = digits.slice(2);
        else if (digits.startsWith('0')) digits = digits.slice(1);

        im.setValue(PREFIX + digits);
    };

    el.addEventListener('keydown', (e) => {
        if (!el || el.selectionStart == null) return;
        if (!e || !e.key) return;

        const pos = el.selectionStart ?? 0;
        if (pos <= PREFIX.length && ['Backspace', 'Delete', 'ArrowLeft', 'Home'].includes(e.key)) {
            e.preventDefault();
            el.setSelectionRange?.(PREFIX.length, PREFIX.length);
        }
    });

    el.addEventListener('input', ensurePrefix);
    el.addEventListener('focus', () => {
        if (!el) return;
        if (!el.value) im.setValue(PREFIX);
        if (el.value && el.setSelectionRange) {
            setTimeout(() => {
                if (el && el.value) el.setSelectionRange(el.value.length, el.value.length);
            }, 0);
        }
    });

    el.__uaPhoneMasked = true;
};

// На случай раннего x-init
window.dispatchEvent(new Event('ua-phone-mask-ready'));

// 3) Регистрируем Alpine компоненты/сторы ДО Alpine.start()
function registerAlpineComponents() {
    Alpine.data('scrollTabs', scrollTabs);
    Alpine.data('authModal', authModal);

    Alpine.data('menuList', (opts = {}) => ({
        active: opts.initial || null,
        remember: !!opts.remember,
        storageKey: opts.storageKey || 'burger.menu.active',
        init() {
            if (this.remember) {
                const saved = localStorage.getItem(this.storageKey);
                if (saved) this.active = saved;
                this.$watch('active', v => localStorage.setItem(this.storageKey, v ?? ''));
            }
        },
        setActive(k) { this.active = k; },
        isActive(k) { return this.active === k; },
    }));

    // cart cache (одна версия)
    window.__CART_CACHE__ = window.__CART_CACHE__ || {
        data: null,
        loading: false,
        promise: null,
        timestamp: 0,
        TTL: 5000,
        async get() {
            if (this.data && (Date.now() - this.timestamp) < this.TTL) return this.data;
            if (this.loading && this.promise) return this.promise;

            this.loading = true;
            this.promise = fetch('/cart/info', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    this.data = data;
                    this.timestamp = Date.now();
                    this.loading = false;
                    this.promise = null;
                    return data;
                })
                .catch(() => {
                    this.loading = false;
                    this.promise = null;
                    return { items: [], qty: 0, total_price: 0 };
                });

            return this.promise;
        },
        invalidate() { this.data = null; this.timestamp = 0; }
    };

    window.addEventListener('cart-updated', () => window.__CART_CACHE__?.invalidate?.());

    registerCartActions(Alpine);
    registerFavoriteButton(Alpine);

    registerCartStore(Alpine, {
        infoUrl: '/cart/info',
        initQty: Number(window.__CART_INIT__?.qty ?? 0),
        initTotal: Number(window.__CART_INIT__?.total ?? 0),
    });

    registerFavoriteStore(Alpine, {
        infoUrl: '/favorites/info',
        initQty: Number(window.__FAVORITES_INIT__?.qty ?? 0),
    });

    Alpine.store('authModal', { open: false });
    window.addEventListener('open-auth-modal', () => {
        window.location.href = '/auth';
    });
}

document.addEventListener('alpine:init', () => {
    registerAlpineComponents();

    Alpine.magic('t', el => (key, params = {}) => {
        const i18n = Alpine.$data(el)?.i18n || window.ST || {};
        let s = i18n[key] ?? key;
        for (const k in params) s = s.replace(new RegExp(':' + k + '\\b', 'g'), String(params[k]));
        return s;
    });

    Alpine.data('cartBadge', ({ initQty = 0, infoUrl = '/cart/info' } = {}) => ({
        qty: Number(initQty) || 0,
        infoUrl,
        async init() {
            await this.refresh();
            window.addEventListener('cart-updated', (e) => {
                if (e?.detail?.qty !== undefined) this.qty = Number(e.detail.qty) || 0;
                else this.refresh();
            });
        },
        async refresh() {
            try {
                const data = window.__CART_CACHE__ ? await window.__CART_CACHE__.get() : null;
                if (data && data.qty !== undefined) this.qty = Number(data.qty) || 0;
            } catch (_) {}
        },
    }));
});

// 4) Swiper — как было
function initBannerSwiper() {
    const bannerEl = document.querySelector('.banner-swiper');
    if (!bannerEl) return;
    if (bannerEl.swiper) return;

    new Swiper('.banner-swiper', {
        modules: [Navigation, Pagination, Autoplay],
        loop: true,
        autoplay: { delay: 4000, disableOnInteraction: false, pauseOnMouseEnter: false },
        slidesPerView: 'auto',
        centeredSlides: true,
        spaceBetween: 24,
        speed: 600,
        pagination: {
            el: '#banner-pagination',
            clickable: true,
            renderBullet: (index, className) =>
                `<span class="${className} inline-block w-2 h-2 rounded-full mx-1"></span>`,
        },
        navigation: {
            nextEl: '.banner-swiper .swiper-button-next',
            prevEl: '.banner-swiper .swiper-button-prev',
        },
    });
}

document.addEventListener('DOMContentLoaded', initBannerSwiper);
document.addEventListener('alpine:init', () => setTimeout(initBannerSwiper, 100));

// 5) Глобальный 419 перехват — оставляем (как у тебя)
(function () {
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                if (response.status === 419) {
                    window.location.reload();
                    return Promise.reject(new Error('CSRF token expired'));
                }
                return response;
            });
    };

    const originalXHROpen = XMLHttpRequest.prototype.open;
    const originalXHRSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url, ...args) {
        this._url = url;
        return originalXHROpen.apply(this, [method, url, ...args]);
    };

    XMLHttpRequest.prototype.send = function (...args) {
        this.addEventListener('load', function () {
            if (this.status === 419) window.location.reload();
        });
        return originalXHRSend.apply(this, args);
    };
})();

// 6) ✅ ВАЖНО: checkout модуль грузим ДО Alpine.start(), чтобы он успел подписаться на alpine:init
(async () => {
  //  console.log('[app] page =', document.body?.dataset?.page);

    if (document.body?.dataset?.page === 'checkout') {
  //      console.log('[app] importing checkout module...');
        await import('./alpine/checkout');
    //    console.log('[app] checkout module imported');
    }

    Alpine.start();
   // console.log('[app] Alpine started');

    // предзагрузка корзины
    window.__CART_CACHE__?.get?.().catch(() => {});
})();
