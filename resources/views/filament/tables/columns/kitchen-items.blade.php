@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
@endphp

@push('styles')
    <style>
        .fi-ta-table tbody.whitespace-nowrap td.kitchen-items-cell,
        .fi-ta-table tbody.whitespace-nowrap td.kitchen-items-cell * {
            white-space: normal !important;
        }
        .kitchen-items-cell,
        .kitchen-items-header {
            width: 200px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            flex: 0 0 200px !important;
            overflow: visible !important;
            white-space: normal !important;
        }
        td.kitchen-items-cell {
            white-space: normal !important;
        }
        .kitchen-items-cell .fi-ta-cell {
            width: 200px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            overflow: visible !important;
            white-space: normal !important;
        }
        .kitchen-items-cell .fi-ta-col-wrp,
        .kitchen-items-cell .fi-ta-col-wrp > *,
        .kitchen-items-cell .fi-ta-col-wrp > * > * {
            white-space: normal !important;
        }
        .kitchen-items-cell .fi-ta-col-wrp > * {
            flex-wrap: wrap !important;
            min-width: 0 !important;
        }
        .kitchen-items-cell .fi-ta-col-wrp > button {
            flex-wrap: wrap !important;
        }
        .kitchen-items-cell .fi-ta-col-wrp > button > * {
            min-width: 0 !important;
        }
        .kitchen-items-cell button,
        .kitchen-items-cell button > * {
            white-space: normal !important;
        }
        .kitchen-items-cell .truncate {
            overflow: visible !important;
            text-overflow: unset !important;
            white-space: normal !important;
        }
    </style>
@endpush

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-xs leading-snug text-gray-700 whitespace-normal break-words w-full min-w-0">
        @foreach ($items as $it)
            @php
                $name = $it->product_short ?: $it->product_name ?: '—';
                $qty = rtrim(rtrim(number_format((float) $it->qty, 2, '.', ' '), '0'), '.');
            @endphp
            <div class="whitespace-normal break-words w-full min-w-0">
                <span class="font-semibold text-sm" style="color: #dc2626;">{{ $qty }} x</span>
                <span class="ml-1 break-words">{{ $name }}</span>
            </div>
        @endforeach
    </div>
@endif
