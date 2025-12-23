import './bootstrap';
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import './alpine/checkout';
import Inputmask from "inputmask";

import registerFavoriteButton from './components/favoriteButton.js'
// ⬇️ новый импорт
import authModal from './alpine/auth-modal';
import scrollTabs from './alpine/scroll-tabs'
import registerCartActions, { registerCartStore } from './alpine/cart-actions';
import { registerFavoriteStore } from './alpine/favorite-store';
// ---- Alpine
function getCsrf() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

function normalizePhone(val){
    let d = String(val || '').replace(/\D/g,'');
    if (d.startsWith('0')) d = '38' + d;
    if (d.length === 9)  d = '380' + d;
    if (!d.startsWith('380') && d.length >= 10) d = '380' + d.slice(-9);
    return d;
}
window.normalizePhone = normalizePhone; // чтобы был доступен везде

function registerAlpineComponents() {
    Alpine.data('scrollTabs', scrollTabs)
    Alpine.data('authModal', authModal);   // ⬅️ регистрируем вынесенный модуль
    
    // Регистрируем menuList для бургер-меню
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
        setActive(k){ this.active = k },
        isActive(k){ return this.active === k },
    }));
    
    registerCartActions(Alpine);
    registerFavoriteButton(Alpine)
    registerCartStore(window.Alpine, {
        infoUrl: '/cart/info',
        // можно задать стартовые значения, если рендеришь с сервера:
        initQty:  Number(window.__CART_INIT__?.qty   ?? 0),
        initTotal:Number(window.__CART_INIT__?.total ?? 0),
    });
    
    // Регистрируем store для избранного
    registerFavoriteStore(window.Alpine, {
        infoUrl: '/favorites/info',
        initQty: Number(window.__FAVORITES_INIT__?.qty ?? 0),
    });
    
    // Хранилище + событие открытия (если нужно)
    Alpine.store('authModal', { open:false });
    window.addEventListener('open-auth-modal', () => Alpine.store('authModal').open = true);
}
// если Alpine уже загрузился — регистрируем сразу,
// если ещё нет — дождёмся события инициализации
if (window.Alpine) {
    registerAlpineComponents()
} else {
    window.addEventListener('alpine:init', registerAlpineComponents)
}
// ---- Swiper
document.addEventListener('DOMContentLoaded', () => {
    new Swiper('.banner-swiper', {
        modules: [Navigation, Pagination, ], //Autoplay
        loop: true,
        autoplay: { delay: 4000, disableOnInteraction: false },
        slidesPerView: 'auto',
        centeredSlides: true,
        spaceBetween: 24,
        // точки в отдельном div под слайдером
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
});
document.addEventListener('alpine:init', () => {
    Alpine.magic('t', el => (key, params={}) => {
        const i18n = Alpine.$data(el)?.i18n || window.ST || {};
        let s = i18n[key] ?? key;
        for (const k in params) s = s.replace(new RegExp(':'+k+'\\b','g'), String(params[k]));
        return s;
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('cartBadge', ({ initQty = 0, infoUrl = '/cart/info' } = {}) => ({
        qty: Number(initQty) || 0,
        infoUrl,

        async init() {
            // 1) сразу попробуем подтянуть актуальное
            await this.refresh();

            // 2) слушаем глобальные обновления корзины
            window.addEventListener('cart-updated', (e) => {
                if (e?.detail?.qty !== undefined) {
                    this.qty = Number(e.detail.qty) || 0;
                } else {
                    this.refresh(); // если qty не пришёл — дотянем сами
                }
            });
        },

        async refresh() {
            try {
                const res = await fetch(this.infoUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin', // важно для сессии
                    cache: 'no-store',          // не брать из кэша
                });
                const data = await res.json();
                if (data && data.qty !== undefined) {
                    this.qty = Number(data.qty) || 0;
                }
            } catch (_) { /* ignore */ }
        },
    }));
});



window.applyUaPhoneMask = function (el) {
    if (!el || el.__uaPhoneMasked) return;

    const PREFIX = '+38 0 ';
    const im = new Inputmask({
        mask: '+38 0 99 999 99 99',
        placeholder: ' ',
        showMaskOnHover: false,
        showMaskOnFocus: true,
        clearIncomplete: true,
    });
    im.mask(el);

    if (!el.value || !el.value.startsWith(PREFIX)) im.setValue(PREFIX);

    const ensurePrefix = () => {
        // берём только цифры и срезаем ведущие 380 / 38 / 0
        let digits = el.value.replace(/\D/g, '');
        if (digits.startsWith('380')) digits = digits.slice(3);
        else if (digits.startsWith('38')) digits = digits.slice(2);
        else if (digits.startsWith('0')) digits = digits.slice(1);

        im.setValue(PREFIX + digits);
    };

    el.addEventListener('keydown', (e) => {
        const pos = el.selectionStart ?? 0;
        if (pos <= PREFIX.length && ['Backspace','Delete','ArrowLeft','Home'].includes(e.key)) {
            e.preventDefault();
            el.setSelectionRange(PREFIX.length, PREFIX.length);
        }
    });

    el.addEventListener('input', ensurePrefix);
    el.addEventListener('focus', () => {
        if (!el.value) im.setValue(PREFIX);
        setTimeout(() => el.setSelectionRange(el.value.length, el.value.length));
    });



    el.__uaPhoneMasked = true;
};





// Сообщаем, что функция готова (на случай раннего x-init)
window.dispatchEvent(new Event('ua-phone-mask-ready'));



