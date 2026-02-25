<div
    x-data="{
        loading: false,
        q: '',
        category: '',
        categories: [],
        products: [],
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
                const params = new URLSearchParams({ q: this.q || '', category_id: this.category || '' });
                const response = await fetch(`${this.fetchUrl}?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    this.products = [];
                    return;
                }

                const payload = await response.json();
                this.categories = Array.isArray(payload.categories) ? payload.categories : [];
                this.products = Array.isArray(payload.products) ? payload.products : [];
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
            <div class="rounded-lg border border-gray-200 p-2">
                <div class="flex gap-2">
                    <img
                        :src="product.image || '/images/placeholder-4x3.jpg'"
                        alt=""
                        class="rounded object-cover flex-none"
                        style="width: 100px; height: 80px;"
                    />
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
            </div>
        </template>
    </div>
</div>
