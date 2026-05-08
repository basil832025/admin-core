export default function scrollTabs() {
    return {
        canScrollLeft: false,
        canScrollRight: false,

        ensureActiveTabVisible() {
            const scroller = this.$refs.scroller;
            const activeTab = this.$refs.activeTab;

            if (!scroller || !activeTab) {
                return;
            }

            const scrollerRect = scroller.getBoundingClientRect();
            const tabRect = activeTab.getBoundingClientRect();
            const leftPadding = 24;
            const rightPadding = 24;

            const isHiddenLeft = tabRect.left < (scrollerRect.left + leftPadding);
            const isHiddenRight = tabRect.right > (scrollerRect.right - rightPadding);

            if (!isHiddenLeft && !isHiddenRight) {
                return;
            }

            const deltaLeft = tabRect.left - scrollerRect.left - leftPadding;
            const deltaRight = tabRect.right - scrollerRect.right + rightPadding;
            const nextScrollLeft = scroller.scrollLeft + (isHiddenLeft ? deltaLeft : deltaRight);

            scroller.scrollTo({
                left: Math.max(0, nextScrollLeft),
                behavior: 'smooth',
            });
        },

        init() {
            const el = this.$refs.scroller;
            const check = () => {
                const max = el.scrollWidth - el.clientWidth - 1;
                this.canScrollLeft  = el.scrollLeft > 0;
                this.canScrollRight = el.scrollLeft < max;
            };
            this.check = check;

            check();
            requestAnimationFrame(() => this.ensureActiveTabVisible());
            el.addEventListener('scroll', check, { passive: true });
            window.addEventListener('resize', check);
            new ResizeObserver(check).observe(el);

            el.addEventListener('wheel', (e) => {
                if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                    el.scrollLeft += e.deltaY;
                    e.preventDefault();
                }
            }, { passive: false });

            if (document.fonts) {
                document.fonts.ready.then(() => {
                    check();
                    this.ensureActiveTabVisible();
                });
            }
        },

        scroll(dir) {
            const el = this.$refs.scroller;
            const step = Math.round(el.clientWidth * 0.8);
            el.scrollBy({ left: dir === 'left' ? -step : step, behavior: 'smooth' });
        },
    };
}
