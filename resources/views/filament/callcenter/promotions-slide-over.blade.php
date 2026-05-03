<div class="cc-promo space-y-4">
    <style>
        .cc-promo-title {
            font-size: 20px;
            line-height: 26px;
            font-weight: 600;
        }
        .cc-promo-title--active {
            color: #15803d; /* green */
        }
        .cc-promo-title--inactive {
            color: #9f1239; /* bordeaux */
        }

        .cc-promo-row--disabled {
            opacity: 0.55;
        }

        .cc-promo input[type="radio"]:disabled {
            opacity: 0.35;
            filter: grayscale(1);
            accent-color: #9ca3af;
        }
    </style>
    <div class="text-lg font-semibold">Доступні акції</div>

    <div class="space-y-2">
        <label class="flex items-start gap-2 cursor-pointer">
            <input type="radio" name="cc_promo" value="none" wire:click="applyPromoSelection('none')" @checked($selected === 'none')>
            <div>
                <div class="font-medium">Без акції</div>
            </div>
        </label>

        @foreach($promos as $promo)
            @php
                $disabled = empty($promo['is_active']);
                $value = (string) ($promo['value'] ?? '');
                $discount = (float) ($promo['discount'] ?? 0);
                $final = (float) ($promo['final'] ?? 0);

                $accentClass = $disabled ? 'cc-promo-title--inactive' : 'cc-promo-title--active';

            @endphp
            <label class="flex items-start gap-3 {{ $disabled ? 'cc-promo-row--disabled cursor-not-allowed' : 'cursor-pointer' }}">
                <input
                    type="radio"
                    name="cc_promo"
                    value="{{ $value }}"
                    wire:click="applyPromoSelection('{{ $value }}')"
                    @checked($selected === $value)
                    @disabled($disabled)
                >
                <div class="w-full">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="cc-promo-title {{ $accentClass }}">{{ $promo['label'] ?? $value }}</div>
                            @if(!empty($promo['description']))
                                <div class="text-xs text-gray-500">{{ $promo['description'] }}</div>
                            @endif
                        </div>
                        <div class="text-right whitespace-nowrap">
                            <div class="text-sm">-{{ number_format($discount, 2, ',', ' ') }} грн</div>
                            <div class="text-[16px] font-semibold">{{ number_format($final, 2, ',', ' ') }} грн</div>
                        </div>
                    </div>
                </div>
            </label>
        @endforeach
    </div>
</div>
