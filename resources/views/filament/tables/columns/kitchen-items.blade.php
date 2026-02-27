@php
    $record = $getRecord();
    $items = ($record?->items ?? collect())->sortBy('id');
    $orderKitchenNote = trim((string) ($record?->order?->kitchen_note ?? ''));
@endphp

@if ($items->isEmpty())
    <span class="text-gray-400">—</span>
@else
    <div class="text-xs leading-snug text-gray-700">
        @if($orderKitchenNote !== '')
            <div style="display:block;margin:0 0 6px 0.35rem;color:#b91c1c !important;background:#fee2e2;padding:3px 7px;border-radius:7px;">
                {{ $orderKitchenNote }}
            </div>
        @endif

        @foreach ($items as $it)
            @php
                $name = $it->product_short ?: $it->product_name ?: '—';
                $qty = rtrim(rtrim(number_format((float) $it->qty, 2, '.', ' '), '0'), '.');
                $kitchenNote = trim((string) ($it->orderItem?->kitchen_note ?? ''));
            @endphp
            <div style="display:block;">
                <span class="font-semibold text-sm" style="color: #dc2626;">{{ $qty }} x</span>
                <span class="ml-1">{{ $name }}</span>
                @if($kitchenNote !== '')
                    <div class="mt-0.5 leading-snug" style="display:block;margin-left:1.5rem;color:#0369a1 !important;background:#eff6ff;padding:2px 6px;border-radius:6px;">
                        {{ $kitchenNote }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
