{{-- Переключатель способа получения --}}
<div class="flex flex-col sm:flex-row items-center justify-center gap-3">
    {{-- Самовывоз --}}
    <button type="button"
            @click="method = 'pickup'"
            :class="method==='pickup'
                ? 'bg-[#FF7500] text-white border-[#FF7500]'
                : 'bg-white text-gray-700 border-neutral-200 hover:border-neutral-300'"
            class="h-10 w-full sm:w-[360px] rounded-[6px] border shadow-[0_2px_10px_rgba(0,0,0,.08)] font-medium">
        {{ st('cart.samovyvoz', 'Самовивіз') }}
    </button>

    {{-- Доставка --}}
    <button type="button"
            @click="method = 'delivery'"
            :class="method==='delivery'
                ? 'bg-[#FF7500] text-white border-[#FF7500]'
                : 'bg-white text-gray-700 border-neutral-200 hover:border-neutral-300'"
            class="h-10 w-full sm:w-[360px] rounded-[6px] border shadow-[0_2px_10px_rgba(0,0,0,.08)] font-medium">
        {{ st('cart.dostavka', 'Доставка') }}
    </button>
</div>

{{-- Скрытый инпут для формы (сохранит выбор) --}}
<input type="hidden" name="shipping_method" :value="method">
