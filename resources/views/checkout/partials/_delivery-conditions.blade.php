@php
    $deliveryMode = $deliveryMode ?? old('delivery_mode', session('checkout.form_data.delivery_mode', 'asap'));
    $timeIntervals = $timeIntervals ?? [];
@endphp
<div
    x-data="deliveryBlock()"
    x-init="
        mode = '{{ $deliveryMode }}';
        allTimeIntervals = @js($timeIntervals ?? []);
        availableTimeIntervals = allTimeIntervals || [];
        savedTime = '{{ old('delivery_time', $sessionData['delivery_time'] ?? '') }}';
        init();
        $nextTick(() => {
            // Восстанавливаем значение после фильтрации интервалов
            if (savedTime && availableTimeIntervals.includes(savedTime)) {
                selectedTime = savedTime;
            }
        });
    "
    class="bg-white rounded shadow-[0_2px_10px_rgba(0,0,0,.08)] pt-3 pr-4 pb-3 pl-4"
>
    <input type="hidden" name="delivery_mode" x-model="mode">

    <div class="text-[18px] md:text-[22px] leading-6 md:leading-7 font-semibold mb-3 md:mb-4">
        {{ st('cart.delivery.conditions_title', 'Умови доставки') }}
    </div>

    <div class="flex flex-col md:flex-row md:items-center gap-4 mb-4">
        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio" value="asap"  x-model="mode" class="tp-radio" @checked($deliveryMode === 'asap')>
            <span class="text-[16px] leading-[22px] text-[#272828]">
                {{ st('cart.delivery.mode.asap', 'Якнайшвидше') }}
            </span>
        </label>

        <label class="inline-flex items-center gap-2 cursor-pointer">
            <input type="radio" value="fixed" x-model="mode" class="tp-radio" @checked($deliveryMode === 'fixed')>
            <span class="text-[16px] leading-[22px] text-[#272828]">
                {{ st('cart.delivery.mode.fixed', 'До визначеного часу') }}
            </span>
        </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Дата* --}}
        <label class="block relative">
            <span class="sr-only">
                {{ st('cart.delivery.date_label', 'Дата') }}
            </span>
            <input
                x-ref="date"
                type="text"
                name="delivery_date"
                placeholder="{{ st('cart.delivery.date_label', 'Дата*') }}"
                value="{{ old('delivery_date', $sessionData['delivery_date'] ?? '') }}"
                :disabled="mode==='asap'"
                :class="[
                    'tp-input pr-10',
                    mode==='asap' ? 'bg-[#F9FAFB] text-[#9CA3AF] cursor-not-allowed' : ''
                ]"
            >
            @error('delivery_date')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
            <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                  :class="mode==='asap' ? 'text-[#9CA3AF]' : 'text-[#FF7500]'">
                {{-- иконка календаря --}}
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path d="M18 4H6C3.79086 4 2 5.79086 2 8V18C2 20.2091 3.79086 22 6 22H18C20.2091 22 22 20.2091 22 18V8C22 5.79086 20.2091 4 18 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 2V6M16 2V6M2 10H22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </label>

        {{-- Время --}}
        <label class="block relative">
            <span class="sr-only">
                {{ st('cart.delivery.time_label', 'Час') }}
            </span>
            <select
                x-ref="time"
                name="delivery_time"
                :disabled="mode==='asap'"
                :class="[
                    'tp-input pr-10 appearance-none',
                    mode==='asap' ? 'bg-[#F9FAFB] text-[#9CA3AF] cursor-not-allowed' : ''
                ]"
                x-model="selectedTime"
                @change="
                    // Сохраняем в сессию при изменении
                    const form = document.querySelector('[data-checkout-form]');
                    if (form) {
                        const event = new Event('change');
                        form.dispatchEvent(event);
                    }
                "
            >
                <option value="">{{ st('cart.delivery.time_label', 'Час') }}</option>
                <template x-for="interval in availableTimeIntervals" :key="interval">
                    <option :value="interval" x-text="interval"></option>
                </template>
            </select>
            @error('delivery_time')
            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#9CA3AF] pointer-events-none">
                <svg viewBox="0 0 24 24" class="w-5 h-5">
                    <path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </label>
    </div>
</div>
