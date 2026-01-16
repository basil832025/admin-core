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
