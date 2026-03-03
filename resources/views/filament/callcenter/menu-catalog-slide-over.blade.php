<div
    x-data="{
        loading: false,
        q: '',
        category: '',
        source: @js((string) ($defaultSourceId ?? '0')),
        sources: [],
        categories: [],
        products: [],
        previewProductId: null,
        timer: null,
        componentId: @js($componentId),
        fetchUrl: @js($fetchUrl),

        init() {
            this.load();
        },

        debounceLoad() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.load(), 220);
        },

        async load() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    q: this.q || '',
                    category_id: this.category || '',
                    source_id: this.source || '0',
                });
                const response = await fetch(`${this.fetchUrl}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    this.products = [];
                    return;
                }

                const payload = await response.json();
                this.sources = Array.isArray(payload.sources) ? payload.sources : [];
                this.categories = Array.isArray(payload.categories) ? payload.categories : [];
                this.products = Array.isArray(payload.products) ? payload.products : [];

                if (!this.sources.find((s) => String(s.id) === String(this.source)) && this.sources.length) {
                    this.source = String(this.sources[0].id);
                }
            } finally {
                this.loading = false;
            }
        },

        async addProduct(productId) {
            if (!window.Livewire || !this.componentId) return;
            const cmp = window.Livewire.find(this.componentId);
            if (!cmp) return;

            await cmp.call('addMenuProductToOrder', Number(productId));
        },

        openDescription(product) {
            this.previewProductId = Number(product?.id || 0);
        },

        closeDescription() {
            this.previewProductId = null;
        },

        isDescriptionOpen(product) {
            return Number(this.previewProductId || 0) === Number(product?.id || -1);
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
    <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
        <x-filament::input.wrapper>
            <x-filament::input
                x-model="q"
                x-on:input="debounceLoad()"
                type="text"
                placeholder="Пошук товарів..."
            />
        </x-filament::input.wrapper>

        <div class="text-xs text-gray-500 self-center" x-show="loading">Завантаження...</div>
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
                        <img
                            :src="resolveImage(product)"
                            alt=""
                            class="rounded object-cover"
                            style="width: 100px; height: 80px;"
                        />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-semibold" x-text="product.title"></div>

                        <template x-if="!product.has_variants">
                            <div class="mt-2 flex items-center justify-between gap-2 rounded bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                                <div>
                                    <span x-text="product.unit || 'Порція'"></span>
                                    <span class="mx-1">·</span>
                                    <span x-text="product.price"></span>
                                    <span> грн</span>
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
                                <div class="truncate">
                                    <span x-text="variant.unit || variant.title"></span>
                                    <span class="mx-1">·</span>
                                    <span x-text="variant.price"></span>
                                    <span> грн</span>
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
</div>
