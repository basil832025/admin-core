@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
    $orderKitchenNote = trim((string) ($record?->notes ?? ''));
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">вЂ”</span>
@else
    <div class="text-[11px] leading-tight text-gray-700">
        @foreach ($items as $item)
            @php
                $title = $item->product?->display_name
                    ?? data_get($item->meta, 'title')
                    ?? 'вЂ”';
                $qty = rtrim(rtrim(number_format((float) $item->qty, 2, '.', ' '), '0'), '.');
                $kitchenNote = trim((string) ($item->kitchen_note ?? ''));
            @endphp
            <div class="mb-1.5 min-w-0">
                <div class="flex min-w-0 items-center gap-1 overflow-hidden">
                    <span class="shrink-0 font-medium text-[10px]" style="color: #dc2626;">{{ $qty }} x</span>
                    <span class="block min-w-0 truncate text-[11px]" style="white-space: nowrap;">{{ $title }}</span>
                </div>

                @if ($kitchenNote !== '')
                    <div class="mt-1 block max-w-full rounded px-1.5 py-0.5 text-[10px] font-medium leading-snug break-words whitespace-normal" style="background:#fef08a;color:#713f12;overflow-wrap:anywhere;">
                        РџСЂРёРјС–С‚РєР°: {{ $kitchenNote }}
                    </div>
                @endif
            </div>
        @endforeach

        @if ($orderKitchenNote !== '')
            <div class="mt-1.5 block max-w-full rounded px-2 py-1 text-[10px] font-semibold leading-snug break-words whitespace-normal" style="background:#fde047;color:#713f12;overflow-wrap:anywhere;">
                {{ $orderKitchenNote }}
            </div>
        @endif
    </div>
@endif

