@props([
'title' => 'Хіти',
// сейчас можно передавать простой массив; позже будет коллекция моделей
'items' => [],
'favoriteIds' => [],
// опционально «Показати все»
'moreUrl' => null,
])

<section class="max-w-screen-xl mx-auto">
    <div class="flex items-end justify-between ">
        <x-product.section-title>{{ $title }}</x-product.section-title>

        @if ($moreUrl)
            <a href="{{ $moreUrl }}" class="text-[#FF7500] hover:underline">Показати все</a>
        @endif
    </div>

    <!-- отступ 32px до сетки  -->
    <div class="md:mt-8 mt-6 grid grid-cols-1 gap-4 desk:gap-12 md:gap-8 md:grid-cols-2 lg:grid-cols-3 " data-product-grid>
        @forelse ($items as $p)
            @php
                $pid    = $p['root_id'] ?? null;                    // из презентера
                $isFav  = $pid ? in_array($pid, $favoriteIds, true) : false;
             //   dd($p,$isFav,$pid);
            @endphp
            <x-product.card
                :product-id="$pid"
                :is-favorite="$isFav"
                :title="$p['title'] ?? 'Товар'"
                :url="$p['url'] ?? ''"
                :article="$p['article'] ?? '12345'"
                :price="$p['price'] ?? '0.00'"
                :description="$p['card_description'] ?? ($p['description'] ?? '')"
                :price_no_sale="$p['old_price'] ?? $p['price_no_sale'] ?? null"
                :image="$p['main_image'] ?? '/images/no-image.svg'"
                :characteristics="$p['characteristics'] ?? []"   {{-- 👈 добавили --}}
                :rows="$p['variant_rows'] ?? []"
                :root_id="$p['root_id'] ?? null"   {{-- 👈 --}}
            />
        @empty
            @for ($i=0; $i<6; $i++)

            @endfor
        @endforelse
    </div>
</section>

@once
    <script>
        (() => {
            if (window.__productCardEqualizerInitialized) {
                return;
            }
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
                        const grid = entry.target.closest('[data-product-grid]');
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

                            if (node.matches('[data-product-grid]')) {
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
    </script>
@endonce
