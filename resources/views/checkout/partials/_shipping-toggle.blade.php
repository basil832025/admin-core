{{-- Блок «Тип заказа» — карточка как у «Контактні дані» / «Адреса доставки» --}}
<div class="bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.08)] p-4">
    {{-- Заголовок только для мобильной/планшетной версии (< 1024px) --}}
    <div class="lg:hidden text-[18px] md:text-[22px] leading-6 md:leading-7 font-semibold mb-3 md:mb-4">
        {{ st('cart.order_type', 'Тип заказа') }}
    </div>

    {{-- Переключатель способа получения --}}
    <div class="flex flex-row items-center justify-center gap-2 md:gap-3" x-cloak>
        {{-- Самовывоз --}}
        <button type="button"
                @click="method = 'pickup'; $nextTick(() => { const event = new Event('change'); document.querySelector('[data-checkout-form]')?.dispatchEvent(event); })"
                :class="(typeof method !== 'undefined' && method === 'pickup')
                    ? 'bg-[#FF7500] text-white border-[#FF7500]'
                    : 'bg-white text-gray-700 border-neutral-200 hover:border-neutral-300'"
                class="h-10 flex-1 md:flex-none md:w-[360px] rounded-[6px] border shadow-[0_2px_10px_rgba(0,0,0,.08)] font-medium text-sm md:text-base">
            {{ st('cart.samovyvoz', 'Самовивіз') }}
        </button>

        {{-- Доставка --}}
        <button type="button"
                @click="method = 'delivery'; $nextTick(() => { const event = new Event('change'); document.querySelector('[data-checkout-form]')?.dispatchEvent(event); })"
                :class="(typeof method !== 'undefined' && method === 'delivery')
                    ? 'bg-[#FF7500] text-white border-[#FF7500]'
                    : 'bg-white text-gray-700 border-neutral-200 hover:border-neutral-300'"
                class="h-10 flex-1 md:flex-none md:w-[360px] rounded-[6px] border shadow-[0_2px_10px_rgba(0,0,0,.08)] font-medium text-sm md:text-base">
            {{ st('cart.dostavka', 'Доставка') }}
        </button>
    </div>

    {{-- Скрытый инпут для формы (сохранит выбор) --}}
    <input type="hidden" name="shipping_method" :value="typeof method !== 'undefined' ? method : ''" x-cloak>
</div>
