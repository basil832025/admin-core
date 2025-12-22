{{-- Использовать бонусы --}}
<div class="space-y-3">
    <label class="flex items-center gap-3 cursor-pointer">
        <span class="relative inline-flex items-center">
            <input
                type="checkbox"
                name="use_bonus"
                value="1"
                class="peer sr-only"
                checked
            >
            <span
                class="w-6 h-6 rounded-[4px] border border-[#FF7500]
                       peer-checked:bg-[#FF7500] grid place-items-center"
            >
                <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path d="M5 13L9 17L19 7"
                          stroke="currentColor" stroke-width="2"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
        </span>

        <span class="text-[14px] leading-[20px] text-[#272828]">
            {{ st('cart.bonus.label', 'Использовать бонусы') }}
            (
            {{ st('cart.bonus.have_prefix', 'у вас') }}
            {{ $userBonusPoints }}
            {{ st('cart.bonus.points_word', 'бонусов') }}
            =
            {{ $bonusLimitMoney }}
            {{ st('cart.summary.currency_short', 'грн') }}
            )
        </span>
    </label>

    {{-- Инпут количества списываемых бонусов + стрелки --}}
    <div
        x-data="{
            value: {{ $bonusUsed > 0 ? (int)$bonusUsed : 0 }},
            min: 0,
            max: {{ (int)$bonusLimitMoney }},
            step: 1,
            syncFromInput() {
                let v = parseInt($refs.bonus.value || 0, 10);
                if (isNaN(v)) v = this.min;
                if (v < this.min) v = this.min;
                if (v > this.max) v = this.max;
                this.value = v;
                $refs.bonus.value = v;
                this.updateTotals();
            },
            change(delta) {
                let v = (this.value || 0) + delta;
                if (v < this.min) v = this.min;
                if (v > this.max) v = this.max;
                this.value = v;
                $refs.bonus.value = v;
                this.updateTotals();
            },
            formatMoney(amount) {
                amount = Number(amount) || 0;
                return amount.toLocaleString('uk-UA', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            },
            formatInt(amount) {
                amount = Number(amount) || 0;
                return amount.toLocaleString('uk-UA', {
                    maximumFractionDigits: 0,
                    useGrouping: true,
                });
            },
            updateTotals() {
                const itemsTotal = {{ (float)$itemsTotal }};
                const baseDiscount = {{ (float)$discount }};
                const bonus = Number(this.value) || 0;
                const discount = Number(baseDiscount) || 0;
                const totalDiscount = discount + bonus;
                let total = itemsTotal - totalDiscount;
                if (total < 0) total = 0;

                const subtotalEl = document.querySelector('[data-checkout-subtotal]');
                const discountEl = document.querySelector('[data-checkout-discount]');
                const bonusEl    = document.querySelector('[data-checkout-bonus]');
                const totalUahEl = document.querySelector('[data-checkout-total-uah]');
                const totalKopEl = document.querySelector('[data-checkout-total-kop]');

                if (subtotalEl) {
                    subtotalEl.textContent = this.formatMoney(itemsTotal) + ' {{ st('cart.summary.currency_short', 'грн') }}';
                }
                if (discountEl) {
                    discountEl.textContent = this.formatMoney(discount) + ' {{ st('cart.summary.currency_short', 'грн') }}';
                }
                if (bonusEl) {
                    bonusEl.textContent = this.formatMoney(bonus);
                }
                if (totalUahEl && totalKopEl) {
                    const uah = Math.floor(total);
                    const kop = Math.round((total - uah) * 100);
                    totalUahEl.textContent = this.formatInt(uah);
                    totalKopEl.textContent = (kop < 10 ? '0' : '') + kop;
                }
            },
            init() {
                this.updateTotals();
            }
        }"
        class="relative w-full"
    >
        <input
            x-ref="bonus"
            type="number"
            name="bonus_amount"
            :value="value"
            min="0"
            max="{{ (int)$bonusLimitMoney }}"
            class="w-full h-[52px] rounded-[8px] border border-[#E5E7EB] px-4 pr-12
                   text-[16px] leading-[22px] placeholder:text-[#9CA3AF]
                   focus:outline-none focus:ring-2 focus:ring-[#FF7500]/20 focus:border-[#FF7500]
                   transition
                   [appearance:textfield]
                   [&::-webkit-inner-spin-button]:appearance-none
                   [&::-webkit-outer-spin-button]:appearance-none"
            @input="syncFromInput()"
        >

        {{-- Стрелки вверх/вниз --}}
        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex flex-col h-8 justify-between">
            <button type="button" class="w-3 h-3 flex items-center justify-center"
                    @click.prevent="change(step)">
                <svg width="10" height="6" viewBox="0 0 10 6" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 5L5 1L9 5" stroke="#272828" stroke-width="1.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            <button type="button" class="w-3 h-3 flex items-center justify-center"
                    @click.prevent="change(-step)">
                <svg width="10" height="6" viewBox="0 0 10 6" fill="none"
                     xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 1L5 5L9 1" stroke="#272828" stroke-width="1.5"
                          stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="mt-1 text-[11px] leading-[14px] text-[#9CA3AF]">
        {{ st('cart.bonus.limit_prefix', 'Можно использовать до') }}
        {{ $bonusLimitMoney }}
        {{ st('cart.summary.currency_short', 'грн') }}
    </div>
</div>
