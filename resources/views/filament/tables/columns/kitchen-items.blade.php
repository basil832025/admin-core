@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-xs leading-snug text-gray-700 whitespace-nowrap overflow-hidden">
        @foreach ($items as $it)
            @php
                $name = $it->product_short ?: $it->product_name ?: '—';
                $qty = rtrim(rtrim(number_format((float) $it->qty, 2, '.', ' '), '0'), '.');
            @endphp
            <div class="whitespace-nowrap truncate overflow-hidden text-ellipsis">
                <span class="font-semibold text-sm" style="color: #dc2626;">{{ $qty }} x</span>
                <span class="ml-1">{{ $name }}</span>
            </div>
        @endforeach
    </div>
@endif
