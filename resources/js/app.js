import './bootstrap';
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';
import scrollTabs from './alpine/scroll-tabs'
import registerFavoriteButton from './components/favoriteButton.js'

// ---- Alpine

function registerAlpineComponents() {
    Alpine.data('scrollTabs', scrollTabs)
    registerFavoriteButton(Alpine)
}
// если Alpine уже загрузился — регистрируем сразу,
// если ещё нет — дождёмся события инициализации
if (window.Alpine) {
    registerAlpineComponents()
} else {
    window.addEventListener('alpine:init', registerAlpineComponents)
}
// меню в бургере
document.addEventListener('alpine:init', () => {
    window.Alpine.data('menuList', (opts = {}) => ({
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
});


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




