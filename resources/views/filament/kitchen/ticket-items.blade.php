@push('styles')
    <style>
        .fi-ta-panel-toggle { display: none !important; }
    </style>
@endpush

@php
    /** @var \App\Models\Kitchen\KitchenTicket|null $record */
    $record = $getRecord();
    $items  = $record?->items()->orderBy('id')->get() ?? collect();
@endphp

@if ($items->isNotEmpty())
    <div class="px-2 py-2">
        <div class="text-xs text-gray-500 mb-1">Наименование · К-во</div>

        <table class="w-full text-sm">
            <tbody>
            @foreach ($items as $it)
                <tr class="border-t first:border-0">
                    <td class="py-1 pr-3 align-top">
                        {{ $it->product_short ?: $it->product_name }}
                    </td>
                    <td class="py-1 text-right tabular-nums align-top">
                        × {{ rtrim(rtrim(number_format((float) $it->qty, 2, '.', ' '), '0'), '.') }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="px-2 py-2 text-sm text-gray-500">Позиции отсутствуют</div>
@endif
