@php
    $address = trim((string) ($address ?? ''));
    $note = trim((string) ($note ?? ''));
@endphp

<div class="space-y-3">
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">
            {{ __('kitchen_ticket.modals.delivery_address') }}
        </div>
        <div class="text-sm font-medium text-gray-900">
            {{ $address !== '' ? $address : __('kitchen_ticket.modals.delivery_address_empty') }}
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">
            {{ __('kitchen_ticket.modals.delivery_note') }}
        </div>
        <div class="text-sm text-gray-700">
            {{ $note !== '' ? $note : __('kitchen_ticket.modals.delivery_note_empty') }}
        </div>
    </div>
</div>
