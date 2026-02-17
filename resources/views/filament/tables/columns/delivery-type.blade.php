@php
    $state = $getState();

    $label = $state === 'pickup'
        ? __('kitchen_ticket.values.pickup')
        : ($state === 'delivery' ? __('kitchen_ticket.values.delivery') : __('kitchen_ticket.values.no'));

    $color = $state === 'pickup'
        ? 'lime'
        : ($state === 'delivery' ? 'sky' : 'gray');

    $recordKey = $recordKey ?? $getRecord()?->getKey();
    $isDelivery = $state === 'delivery';
@endphp

@if ($isDelivery && $recordKey)
    <button
        type="button"
        class="group inline-flex items-center justify-center"
        wire:click.stop.prevent="mountTableAction('delivery_info', '{{ $recordKey }}')"
    >
        <x-filament::badge :color="$color" class="group-hover:ring-1 group-hover:ring-sky-400/60">
            {{ $label }}
        </x-filament::badge>
    </button>
@else
    <x-filament::badge :color="$color">
        {{ $label }}
    </x-filament::badge>
@endif
