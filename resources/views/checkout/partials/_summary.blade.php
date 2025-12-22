@php
    $itemsTotal = $totals['items_total'] ?? ($totals['total_price'] ?? 0);
    $discount   = $totals['discount']    ?? 0;
    $bonusUsed  = $totals['bonus_used']  ?? 0;
    $grandTotal = $totals['grand_total'] ?? max($itemsTotal - $discount - $bonusUsed, 0);

    $userBonusPoints = $totals['bonus_points'] ?? 20;
    $bonusLimitMoney = $totals['bonus_limit']  ?? 120;
@endphp

<div class="bg-white rounded-[12px] shadow-[0_2px_10px_rgba(0,0,0,.08)] pt-6 pr-6 pb-5 pl-6 space-y-5">
    @include('checkout.partials._summary-promo')

    @include('checkout.partials._summary-bonus')

    @include('checkout.partials._summary-totals')
</div>
