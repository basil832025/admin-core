export default function scrollTabs() {
    return {
        canScrollLeft: false,
        canScrollRight: false,

        init() {
            const el = this.$refs.scroller;
            const check = () => {
                const max = el.scrollWidth - el.clientWidth - 1;
                this.canScrollLeft  = el.scrollLeft > 0;
                this.canScrollRight = el.scrollLeft < max;
            };
            this.check = check;

            check();
            el.addEventListener('scroll', check, { passive: true });
            window.addEventListener('resize', check);
            new ResizeObserver(check).observe(el);

            el.addEventListener('wheel', (e) => {
                if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    el.scrollLeft += e.deltaY;
                    e.preventDefault();
                }
            }, { passive: false });

            if (document.fonts) document.fonts.ready.then(check);
        },

        scroll(dir) {
            const el = this.$refs.scroller;
            const step = Math.round(el.clientWidth * 0.8);
            el.scrollBy({ left: dir === 'left' ? -step : step, behavior: 'smooth' });
        },
    };
}
