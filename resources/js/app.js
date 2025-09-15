import './bootstrap';
import Swiper from 'swiper';
import { Navigation, Pagination, Autoplay } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

document.addEventListener('DOMContentLoaded', () => {
    new Swiper('.banner-swiper', {
        modules: [Navigation, Pagination, Autoplay], //
        loop: true,
        autoplay: { delay: 4000, disableOnInteraction: false },
        slidesPerView: 1,
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


