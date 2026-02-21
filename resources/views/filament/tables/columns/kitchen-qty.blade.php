@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-xs leading-snug text-gray-700 tabular-nums text-center">
        @foreach ($items as $it)
            @php
                $qty = rtrim(rtrim(number_format((float) $it->qty, 2, '.', ' '), '0'), '.');
            @endphp
            <div>{{ $qty }} x</div>
        @endforeach
    </div>
@endif
