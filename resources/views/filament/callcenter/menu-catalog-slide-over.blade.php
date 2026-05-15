<div
    x-data="{
        loading: false,
        q: '',
        sort: 'popular',
        sortMenuOpen: false,
        category: '',
        source: @js((string) ($defaultSourceId ?? '0')),
        sources: [],
        categories: [],
        products: [],
        page: 1,
        hasMore: false,
        loadingMore: false,
        previewProductId: null,
        timer: null,
        componentId: @js($componentId),
        fetchUrl: @js($fetchUrl),
        sortLabels: {
            popular: @js(st('catalog.sort.popular', 'Популярні')),
            new: @js(st('catalog.sort.new', 'Новинки')),
            price_asc: @js(st('catalog.sort.price_asc', 'Ціна: за зростанням')),
            price_desc: @js(st('catalog.sort.price_desc', 'Ціна: за спаданням')),
            discount_asc: @js(st('catalog.sort.discount_asc', 'Знижка: за зростанням')),
            discount_desc: @js(st('catalog.sort.discount_desc', 'Знижка: за спаданням')),
        },
        badgeLabels: {
            is_spicy: 'Гострий',
            is_new: 'Новинка',
            is_promo: 'Акція',
            is_hit: 'Хіт',
            is_vegan: 'Веган',
            is_product_of_day: 'Пиріг дня',
        },

        init() {
            this.load();
        },

        get sortLabel() {
            return this.sortLabels[this.sort] || @js(st('catalog.sort.title', 'Сортувати'));
        },

        get sortOptions() {
            return Object.entries(this.sortLabels).map(([value, label]) => ({ value, label }));
        },

        debounceLoad() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.load(), 220);
        },

        async load(reset = true) {
            if (reset) {
                this.page = 1;
                this.loading = true;
            } else {
                if (this.loadingMore || !this.hasMore) {
                    return;
                }

                this.loadingMore = true;
            }

            try {
                const params = new URLSearchParams({
                    q: this.q || '',
                    sort: this.sort || 'popular',
                    category_id: this.category || '',
                    source_id: this.source || '0',
                    page: String(this.page || 1),
                });
                const response = await fetch(`${this.fetchUrl}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    if (reset) {
                        this.products = [];
                    }
                    this.hasMore = false;
                    return;
                }

                const payload = await response.json();
                this.sources = Array.isArray(payload.sources) ? payload.sources : [];
                this.categories = Array.isArray(payload.categories) ? payload.categories : [];
                const nextProducts = Array.isArray(payload.products) ? payload.products : [];
                this.products = reset ? nextProducts : this.products.concat(nextProducts);
                this.hasMore = Boolean(payload.has_more);

                if (!this.sources.find((s) => String(s.id) === String(this.source)) && this.sources.length) {
                    this.source = String(this.sources[0].id);
                }
            } finally {
                if (reset) {
                    this.loading = false;
                } else {
                    this.loadingMore = false;
                }
            }
        },

        async loadMore() {
            if (!this.hasMore || this.loadingMore) {
                return;
            }

            this.page += 1;
            await this.load(false);
        },

        async addProduct(productId) {
            if (!window.Livewire || !this.componentId) return;
            const cmp = window.Livewire.find(this.componentId);
            if (!cmp) return;

            await cmp.call('addMenuProductToOrder', Number(productId), Number(this.source || 0));
        },

        openDescription(product) {
            this.previewProductId = Number(product?.id || 0);
        },

        closeDescription() {
            this.previewProductId = null;
        },

        selectSort(value) {
            this.sort = String(value || 'popular');
            this.sortMenuOpen = false;
            this.load();
        },

        isDescriptionOpen(product) {
            return Number(this.previewProductId || 0) === Number(product?.id || -1);
        },

        hasDiscount(item) {
            const price = Number(item?.price || 0);
            const oldPrice = Number(item?.old_price || 0);

            return oldPrice > 0 && oldPrice > price;
        },

        discountLabel(item) {
            const discount = Number(item?.discount_percent || 0);

            return discount > 0 ? `-${discount}%` : '';
        },

        buildBadges(item) {
            if (!item) {
                return [];
            }

            const items = [];

            if (item.is_spicy) {
                items.push({ key: 'is_spicy', color: '#FF0013', textColor: '#FFFFFF', label: this.badgeLabels.is_spicy });
            }
            if (item.is_new) {
                items.push({ key: 'is_new', color: '#B91C1C', textColor: '#FFFFFF', label: this.badgeLabels.is_new });
            }
            if (item.is_promo) {
                items.push({ key: 'is_promo', color: '#FF7500', textColor: '#FFFFFF', label: this.badgeLabels.is_promo });
            }
            if (item.is_hit) {
                items.push({ key: 'is_hit', color: '#FFD700', textColor: '#19191A', label: this.badgeLabels.is_hit });
            }
            if (item.is_vegan) {
                items.push({ key: 'is_vegan', color: '#27AE60', textColor: '#FFFFFF', label: this.badgeLabels.is_vegan });
            }
            if (item.is_product_of_day) {
                items.push({ key: 'is_product_of_day', color: '#5D4037', textColor: '#FFFFFF', label: this.badgeLabels.is_product_of_day });
            }

            return items;
        },

        formatMoney(value) {
            const amount = Number(value || 0);

            if (!Number.isFinite(amount)) {
                return '0';
            }

            return amount.toLocaleString('uk-UA', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            });
        },

        resolveImage(product) {
            const direct = String(product?.image || '').trim();
            if (direct !== '') {
                return direct;
            }

            const base = String(product?.source_base_url || '').replace(/\/$/, '');
            const fallbackId = String(product?.image_fallback_id || '').trim();

            if (base !== '' && fallbackId !== '') {
                return `${base}/images/catalog_products/${fallbackId}.1.b.png`;
            }

            return '/images/placeholder-4x3.jpg';
        },
    }"
    class="space-y-4"
>
    <div class="flex items-center gap-3 pb-1">
        <div class="shrink-0" style="width: 300px;">
            <x-filament::input.wrapper>
                <x-filament::input
                    x-model="q"
                    x-on:input="debounceLoad()"
                    type="text"
                    placeholder="Пошук товарів..."
                />
            </x-filament::input.wrapper>
        </div>

        <div class="relative shrink-0" style="width: 300px;" @click.outside="sortMenuOpen = false">
            <button
                type="button"
                class="flex h-10 w-full items-center justify-between rounded-xl border border-[#E5E7EB] bg-white px-3 text-left"
                @click="sortMenuOpen = !sortMenuOpen"
            >
                <span class="truncate text-[15px] font-semibold text-[#19191A]" x-text="sortLabel"></span>
                <svg class="h-5 w-5 text-[#19191A] transition-transform" :class="sortMenuOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <div
                x-show="sortMenuOpen"
                x-cloak
                x-transition.origin.top.left
                class="absolute z-30 mt-2 w-full rounded-2xl border border-[#E5E7EB] bg-white p-2 shadow-lg"
            >
                <template x-for="option in sortOptions" :key="option.value">
                    <button
                        type="button"
                        class="block w-full rounded-[10px] px-3 py-2 text-left text-[15px] text-[#19191A] hover:bg-neutral-100"
                        :class="String(sort) === String(option.value) ? 'bg-[#FFE6B8] font-semibold' : ''"
                        @click="selectSort(option.value)"
                        x-text="option.label"
                    ></button>
                </template>
            </div>
        </div>

        <div class="shrink-0 self-center text-xs text-gray-500" x-show="loading">Завантаження...</div>
    </div>

    <div class="flex flex-wrap gap-2">
        <template x-for="site in sources" :key="site.id">
            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :style="String(source) === String(site.id)
                    ? 'background:#111827;color:#ffffff;border-color:#111827;'
                    : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                @click="source = String(site.id); category = ''; load()"
                x-text="site.name"
            ></button>
        </template>
    </div>

    <div class="flex flex-wrap gap-2">
        <button
            type="button"
            class="rounded-lg border px-3 py-1.5 text-xs"
            :style="category === ''
                ? 'background:#111827;color:#ffffff;border-color:#111827;'
                : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
            @click="category = ''; load()"
        >
            Усі
        </button>

        <template x-for="cat in categories" :key="cat.id">
            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :style="String(category) === String(cat.id)
                    ? 'background:#111827;color:#ffffff;border-color:#111827;'
                    : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                @click="category = String(cat.id); load()"
                x-text="cat.name"
            ></button>
        </template>
    </div>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
        <template x-for="product in products" :key="product.id">
            <div class="rounded-lg border border-gray-200 p-2 relative">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="flex-none rounded"
                        @click.stop="openDescription(product)"
                    >
                        <div class="relative overflow-hidden rounded" style="width: 100px; height: 80px;">
                            <img
                                :src="resolveImage(product)"
                                alt=""
                                class="h-full w-full object-cover"
                            />
                            <div class="absolute top-1 z-10 flex flex-col items-end" style="right: 5px; max-width: 76px; gap: 2px;">
                                <template x-if="hasDiscount(product)">
                                    <span class="bg-[#B91C1C] font-bold leading-none text-white" style="border-radius: 2px; padding: 1px 4px; font-size: 12px; line-height: 1;" x-text="discountLabel(product)"></span>
                                </template>
                                <template x-for="badge in buildBadges(product)" :key="badge.key">
                                    <span
                                        x-text="badge.label"
                                        :style="`background:${badge.color};color:${badge.textColor};border-radius:2px;padding:1px 4px;font-size:12px;line-height:1;font-weight:700;max-width:76px;`"
                                    ></span>
                                </template>
                            </div>
                        </div>
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-semibold" x-text="product.title"></div>

                        <template x-if="!product.has_variants">
                            <div class="mt-2 flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span x-text="product.unit || 'Порція'"></span>
                                        <span class="mx-1">·</span>
                                        <span class="font-semibold text-slate-900" x-text="formatMoney(product.price)"></span>
                                        <span> грн</span>
                                        <template x-if="hasDiscount(product)">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-[11px] text-slate-400" style="text-decoration: line-through;" x-text="formatMoney(product.old_price) + ' грн'"></span>
                                                <span class="text-[11px] font-semibold text-[#B91C1C]" x-text="discountLabel(product)"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded text-xs font-bold"
                                    style="width:20px;height:20px;background:#16a34a;color:#fff;line-height:1;border:none;"
                                    @click="addProduct(product.id)"
                                >+</button>
                            </div>
                        </template>

                        <template x-if="product.has_variants">
                            <div class="mt-2 space-y-1">
                        <template x-for="variant in product.variants" :key="variant.id">
                            <div class="flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                                <div class="min-w-0 truncate">
                                    <div class="flex flex-wrap items-center gap-1.5 truncate">
                                        <span x-text="variant.unit || variant.title"></span>
                                        <span class="mx-1">·</span>
                                        <span class="font-semibold text-slate-900" x-text="formatMoney(variant.price)"></span>
                                        <span> грн</span>
                                        <template x-if="hasDiscount(variant)">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="text-[11px] text-slate-400" style="text-decoration: line-through;" x-text="formatMoney(variant.old_price) + ' грн'"></span>
                                                <span class="text-[11px] font-semibold text-[#B91C1C]" x-text="discountLabel(variant)"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded text-xs font-bold"
                                    style="width:20px;height:20px;background:#16a34a;color:#fff;line-height:1;border:none;"
                                    @click="addProduct(variant.id)"
                                >+</button>
                            </div>
                        </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    x-show="isDescriptionOpen(product)"
                    x-cloak
                    @click.outside="closeDescription()"
                    class="absolute left-2 right-2 top-[88px] z-30 rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-xs font-semibold text-slate-900" x-text="product.title"></div>
                        <button
                            type="button"
                            class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] text-slate-600"
                            @click="closeDescription()"
                        >×</button>
                    </div>
                    <div class="mt-2 max-h-28 overflow-auto text-[11px] leading-snug text-slate-700" x-text="product.description || 'Опис відсутній'"></div>
                </div>
            </div>
        </template>
    </div>

    <div class="flex justify-center pt-2" x-show="hasMore">
        <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="loadingMore"
            @click="loadMore()"
        >
            <span x-show="!loadingMore">Показати ще</span>
            <span x-show="loadingMore">Завантаження...</span>
        </button>
    </div>
</div>
