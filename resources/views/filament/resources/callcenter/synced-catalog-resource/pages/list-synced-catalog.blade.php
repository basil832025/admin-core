<x-filament-panels::page>
    <div
        x-data="{
            loading: false,
            source: @js((string) ($defaultSourceId ?? 0)),
            directory: 'catalog',
            q: '',
            category: '',
            sources: [],
            categories: [],
            products: [],
            clients: [],
            timer: null,
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
                        source_id: this.source || '0',
                        directory: this.directory || 'catalog',
                        q: this.q || '',
                        category_id: this.category || '',
                    });

                    const response = await fetch(`${this.fetchUrl}?${params.toString()}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        return;
                    }

                    const payload = await response.json();
                    this.sources = Array.isArray(payload.sources) ? payload.sources : [];
                    this.source = String(payload.selected_source_id ?? this.source ?? '0');

                    if (this.directory === 'catalog') {
                        this.categories = Array.isArray(payload.categories) ? payload.categories : [];
                        this.products = Array.isArray(payload.products) ? payload.products : [];
                    } else {
                        this.clients = Array.isArray(payload.clients) ? payload.clients : [];
                    }
                } finally {
                    this.loading = false;
                }
            },
        }"
        class="space-y-4"
    >
        <div class="flex flex-wrap gap-2">
            <template x-for="site in sources" :key="site.id">
                <button
                    type="button"
                    class="rounded-lg border px-3 py-1.5 text-xs"
                    :style="String(source) === String(site.id)
                        ? 'background:#111827;color:#ffffff;border-color:#111827;'
                        : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                    @click="source = String(site.id); category=''; q=''; load()"
                    x-text="site.name"
                ></button>
            </template>
        </div>

        <div class="flex flex-wrap gap-2">
            <template x-for="site in sources" :key="`sync-${site.id}`">
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-1.5 text-[11px] text-gray-600">
                    <span class="font-semibold" x-text="site.name"></span>
                    <span class="mx-1">·</span>
                    <span>Остання синхронізація:</span>
                    <span class="font-medium" x-text="site.last_catalog_synced_at_label || '—'"></span>
                </div>
            </template>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :style="directory === 'catalog'
                    ? 'background:#111827;color:#ffffff;border-color:#111827;'
                    : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                @click="directory='catalog'; category=''; q=''; load()"
            >
                Каталог
            </button>

            <button
                type="button"
                class="rounded-lg border px-3 py-1.5 text-xs"
                :style="directory === 'clients'
                    ? 'background:#111827;color:#ffffff;border-color:#111827;'
                    : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                @click="directory='clients'; category=''; q=''; load()"
            >
                Клиенты
            </button>

            <div class="text-xs text-gray-500 self-center" x-show="loading">Завантаження...</div>
        </div>

        <div class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto]">
            <x-filament::input.wrapper>
                <x-filament::input
                    x-model="q"
                    x-on:input="debounceLoad()"
                    type="text"
                    x-bind:placeholder="directory === 'catalog' ? 'Пошук товарів...' : 'Пошук клієнтів (імя/телефон/email)...'"
                />
            </x-filament::input.wrapper>
        </div>

        <template x-if="directory === 'catalog'">
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border px-3 py-1.5 text-xs"
                        :style="category === ''
                            ? 'background:#111827;color:#ffffff;border-color:#111827;'
                            : 'background:#ffffff;color:#334155;border-color:#d1d5db;'"
                        @click="category=''; load()"
                    >
                        Усі групи
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
                    <template x-for="product in products" :key="product.external_parent_id || product.id">
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
                                        <div class="mt-2 rounded bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                                            <div>
                                                <span x-text="product.unit || 'Порція'"></span>
                                                <span class="mx-1">·</span>
                                                <span x-text="product.price"></span>
                                                <span> грн</span>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="product.has_variants">
                                        <div class="mt-2 space-y-1">
                                            <template x-for="variant in product.variants" :key="variant.id">
                                                <div class="rounded bg-gray-50 px-2 py-1.5 text-xs text-gray-700">
                                                    <span x-text="variant.unit || variant.title"></span>
                                                    <span class="mx-1">·</span>
                                                    <span x-text="variant.price"></span>
                                                    <span> грн</span>
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
        </template>

        <template x-if="directory === 'clients'">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold">Имя</th>
                            <th class="px-3 py-2 text-left font-semibold">Телефон</th>
                            <th class="px-3 py-2 text-left font-semibold">Email</th>
                            <th class="px-3 py-2 text-left font-semibold">Локальный ID</th>
                        </tr>
                    </thead>

                    <tbody>
                        <template x-for="client in clients" :key="client.id">
                            <tr class="border-t">
                                <td class="px-3 py-2" x-text="client.name || '—'"></td>
                                <td class="px-3 py-2" x-text="client.phone || '—'"></td>
                                <td class="px-3 py-2 max-w-[260px] truncate" x-text="client.email || '—'"></td>
                                <td class="px-3 py-2" x-text="client.local_client_id || '—'"></td>
                            </tr>
                        </template>

                        <template x-if="!clients.length && !loading">
                            <tr class="border-t">
                                <td colspan="4" class="px-3 py-4 text-center text-gray-500">Нет данных по клиентам</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</x-filament-panels::page>
