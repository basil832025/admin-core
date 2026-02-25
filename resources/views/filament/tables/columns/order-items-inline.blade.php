@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-xs leading-snug text-gray-700">
        @foreach ($items as $item)
            @php
                $title = $item->product?->display_name
                    ?? data_get($item->meta, 'title')
                    ?? '—';
                $qty = rtrim(rtrim(number_format((float) $item->qty, 2, '.', ' '), '0'), '.');
            @endphp
            <div class="truncate">
                <span class="font-semibold text-sm" style="color: #dc2626;">{{ $qty }} x</span>
                <span class="ml-1">{{ $title }}</span>
            </div>
        @endforeach
    </div>
@endif
