@php
    $record = $getRecord();
    $recordKey = $recordKey ?? $record?->getKey();
    $orderNumber = $record?->order?->number ?? '—';
    $orderTime = $record?->order?->created_at?->format('d.m H:i');
@endphp

@if ($recordKey)
    <button
        type="button"
        class="group text-left w-full"
        wire:click.stop.prevent="mountTableAction('order_note_info', '{{ $recordKey }}')"
    >
        <div class="font-semibold group-hover:underline">
            {{ $orderNumber }}
        </div>
        @if ($orderTime)
            <div class="text-xs text-gray-500">
                {{ $orderTime }}
            </div>
        @endif
    </button>
@else
    <div class="font-semibold">—</div>
@endif
