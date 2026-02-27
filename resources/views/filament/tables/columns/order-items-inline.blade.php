@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-[11px] leading-tight text-gray-700">
        @foreach ($items as $item)
            @php
                $title = $item->product?->display_name
                    ?? data_get($item->meta, 'title')
                    ?? '—';
                $qty = rtrim(rtrim(number_format((float) $item->qty, 2, '.', ' '), '0'), '.');
            @endphp
            <div class="flex min-w-0 items-center gap-1 overflow-hidden">
                <span class="shrink-0 font-medium text-[10px]" style="color: #dc2626;">{{ $qty }} x</span>
                <span class="block min-w-0 truncate text-[11px]" style="white-space: nowrap;">{{ $title }}</span>
            </div>
        @endforeach
    </div>
@endif
