@php
    $record = $getRecord();
    $cellAddress = $address ?? '—';
    $cellIsPickup = (bool) ($isPickup ?? false);
    $cellCanOpenRoute = (bool) ($canOpenRoute ?? false);
    $cellDeliveryNote = trim((string) ($deliveryNote ?? ''));
    $cellPalette = is_array($palette ?? null) ? $palette : [];
    $bg = $cellPalette['bg'] ?? 'rgba(59, 130, 246, 0.16)';
    $border = $cellPalette['border'] ?? 'rgba(59, 130, 246, 0.48)';
    $text = $cellPalette['text'] ?? '#0f172a';
    $style = "background: {$bg}; border: 1px solid {$border}; color: {$text};";
@endphp

<button
    type="button"
    class="w-full text-left rounded-lg px-3 py-2 transition"
    style="<?php echo e($style); ?>"
    @if($cellCanOpenRoute) wire:click="mountTableAction('route_map', '{{ $record->getKey() }}')" @endif
    @if(! $cellCanOpenRoute) disabled @endif
>
    <div class="text-[13px] font-semibold leading-5">{{ $cellAddress }}</div>
    <div class="mt-1 text-[11px] opacity-80">
        @if($cellIsPickup)
            {{ __('logistics.columns.self_pickup') }}
        @elseif(! $cellCanOpenRoute)
            {{ __('logistics.actions.route_destination_missing') }}
        @else
            {{ __('logistics.actions.route_hint') }}
        @endif
    </div>

    @if($cellDeliveryNote !== '')
        <div class="mt-2 rounded px-2 py-1 text-[11px]" style="background:#f3f4f6;color:#15803d;">
            {{ $cellDeliveryNote }}
        </div>
    @endif
</button>
