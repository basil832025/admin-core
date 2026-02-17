@php
    $note = trim((string) ($note ?? ''));
@endphp

<div class="space-y-3">
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500">
            {{ __('kitchen_ticket.modals.order_note_label') }}
        </div>
        <div class="text-sm text-gray-700">
            {{ $note !== '' ? $note : __('kitchen_ticket.modals.order_note_empty') }}
        </div>
    </div>
</div>
