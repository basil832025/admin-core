{{-- Итоги --}}
<div class="pt-1 space-y-1.5 text-[14px] leading-[20px]">
    <div class="flex justify-between text-[#272828]">
        <span>{{ st('cart.summary.items', 'Товари') }}</span>
        <span data-checkout-subtotal>
            {{ number_format($itemsTotal, 2, ',', ' ') }}
            {{ st('cart.summary.currency_short', 'грн') }}
        </span>
    </div>

    <div class="flex justify-between text-[#272828]">
        <span>{{ st('cart.summary.discount', 'Скидка') }}</span>

        <span class="flex items-baseline gap-1">
        <span data-checkout-discount>
            {{ number_format($discount, 2, ',', ' ') }}
        </span>
        <span>{{ st('cart.summary.currency_short', 'грн.') }}</span>
    </span>
    </div>

    <div class="flex justify-between text-[#272828]">
        <span>{{ st('cart.summary.bonus', 'Бонуси') }}</span>
        <span data-checkout-bonus>
            {{ number_format($bonusUsed, 2, ',', ' ') }}
        </span>
    </div>

    <div class="h-px bg-[#F3F4F6] my-2"></div>

    <div class="flex justify-between items-end">
        <span class="text-[16px] leading-[22px] text-[#272828]">
            {{ st('cart.summary.total', 'Всего') }}
        </span>
        <div class="flex items-baseline gap-1 text-[#111827] font-bold" data-checkout-total-wrapper>
            @php
                $uah = floor($grandTotal);
                $kop = sprintf('%02d', (int)round(($grandTotal - $uah) * 100));
            @endphp
            <span class="text-[28px] leading-none tabular-nums" data-checkout-total-uah>
                {{ number_format($uah, 0, ',', ' ') }}
            </span>
            <sup class="text-[16px] leading-none tabular-nums" data-checkout-total-kop>{{ $kop }}</sup>
            <span class="text-[18px] leading-none ml-1">
                {{ st('cart.summary.currency_short', 'грн') }}
            </span>
        </div>
    </div>
</div>

{{-- Согласие --}}
<label class="mt-1 flex items-start gap-3 text-[11px] leading-[16px] text-[#4B5563]">
    <span class="relative inline-flex items-center pt-[2px]">
        <input
            type="checkbox"
            name="agree"
            value="1"
            class="peer sr-only"
            required
        >
        <span
            class="w-4 h-4 rounded-[4px] border border-[#9CA3AF]
                   peer-checked:bg-[#FF7500] peer-checked:border-[#FF7500]
                   grid place-items-center transition"
        >
            <svg class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="none"
                 xmlns="http://www.w3.org/2000/svg">
                <path d="M5 13L9 17L19 7"
                      stroke="currentColor" stroke-width="2"
                      stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </span>

    <span>
        {{ st('cart.agree.text', 'Я согласен с политикой конфиденциальности, пользовательским соглашением и даю разрешение на обработку персональных данных.') }}
    </span>
</label>

{{-- Кнопка оформления --}}
<button
    type="submit"
    data-checkout-submit
    class="mt-3 w-full h-[52px] rounded-[12px] bg-[#FF7500] hover:bg-[#e56700]
           text-white text-[18px] leading-[24px] font-semibold
           shadow-[0_4px_12px_rgba(255,117,0,0.35)] transition"
>
    {{ st('cart.actions.checkout', 'Оформление заказа') }}
</button>
