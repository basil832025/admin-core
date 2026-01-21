@php
    $itemsTotal = $totals['items_total'] ?? ($totals['total_price'] ?? 0);
    $discount   = $totals['discount']    ?? 0;
    $bonusUsed  = $totals['bonus_used']  ?? 0;
    $grandTotal = $totals['grand_total'] ?? max($itemsTotal - $discount - $bonusUsed, 0);
@endphp

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

