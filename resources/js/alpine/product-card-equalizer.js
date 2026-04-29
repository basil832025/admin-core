// Equalize product card heights inside containers marked with [data-product-grid].
// Used on category pages, search results, recommendations sliders, etc.

(() => {
    if (typeof window === 'undefined') return;
    if (window.__productCardEqualizerInitialized) return;
    window.__productCardEqualizerInitialized = true;

    const media = window.matchMedia('(min-width: 768px)');
    const queue = new Set();

    const equalizeGrid = (grid) => {
        const cards = Array.from(grid.querySelectorAll('[data-product-card]'));

        cards.forEach((card) => {
            card.style.minHeight = '';
        });

        if (!media.matches || cards.length < 2) {
            return;
        }

        const maxHeight = cards.reduce((max, card) => Math.max(max, card.offsetHeight), 0);
        if (!maxHeight) {
            return;
        }

        cards.forEach((card) => {
            card.style.minHeight = `${maxHeight}px`;
        });
    };

    const flushQueue = () => {
        queue.forEach((grid) => equalizeGrid(grid));
        queue.clear();
        flushQueue.raf = null;
    };

    const scheduleEqualize = (grid) => {
        if (!grid) {
            return;
        }

        queue.add(grid);

        if (!flushQueue.raf) {
            flushQueue.raf = requestAnimationFrame(flushQueue);
        }
    };

    const resizeObserver = 'ResizeObserver' in window
        ? new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                const grid = entry.target.closest?.('[data-product-grid]');
                if (grid) {
                    scheduleEqualize(grid);
                }
            });
        })
        : null;

    const initGrid = (grid) => {
        if (!grid || grid.dataset.equalizerReady === '1') {
            return;
        }

        grid.dataset.equalizerReady = '1';

        const cards = Array.from(grid.querySelectorAll('[data-product-card]'));
        cards.forEach((card) => {
            if (resizeObserver) {
                resizeObserver.observe(card);
            }
        });

        grid.querySelectorAll('img').forEach((img) => {
            if (!img.complete) {
                img.addEventListener('load', () => scheduleEqualize(grid), { once: true });
                img.addEventListener('error', () => scheduleEqualize(grid), { once: true });
            }
        });

        scheduleEqualize(grid);
    };

    const initAll = () => {
        document.querySelectorAll('[data-product-grid]').forEach(initGrid);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll, { once: true });
    } else {
        initAll();
    }

    window.addEventListener('resize', () => {
        document.querySelectorAll('[data-product-grid]').forEach(scheduleEqualize);
    }, { passive: true });

    document.addEventListener('variant-selected', (event) => {
        const grid = event.target?.closest?.('[data-product-grid]');
        if (grid) {
            scheduleEqualize(grid);
        }
    });

    if ('MutationObserver' in window) {
        const mutationObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    if (node.matches?.('[data-product-grid]')) {
                        initGrid(node);
                        return;
                    }

                    const grid = node.querySelector?.('[data-product-grid]');
                    if (grid) {
                        initGrid(grid);
                    }
                });
            });
        });

        mutationObserver.observe(document.body, { childList: true, subtree: true });
    }
})();
