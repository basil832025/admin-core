@php
    $record = $getRecord();
    $order = $record?->order;
    $items = $order?->items ?? collect();
    $recordKey = $recordKey ?? $record?->getKey();

    $displayItems = $items->map(function ($item) {
        $title = $item->product?->display_name
            ?? data_get($item->meta, 'title')
            ?? '—';

        return [
            'title' => $title,
            'qty' => (int) ($item->qty ?? 0),
        ];
    });
@endphp

<div class="w-full flex items-center justify-center" x-data="{ open: false }" x-on:click.stop>
    @if ($recordKey === null || $items->isEmpty())
        <span class="text-gray-400">—</span>
    @else
        <div class="flex flex-col items-center">
            <span
                role="button"
                tabindex="0"
                class="fi-icon-btn relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 focus-visible:ring-2"
                x-on:click.stop="open = !open"
                x-on:keydown.enter.stop.prevent="open = !open"
                x-on:keydown.space.stop.prevent="open = !open"
                title="{{ __('kitchen_ticket.columns.items_count') }}"
            >
                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="h-5 w-5"
                    x-show="!open"
                    x-cloak
                />
                <x-filament::icon
                    icon="heroicon-m-chevron-up"
                    class="h-5 w-5"
                    x-show="open"
                    x-cloak
                />
            </span>

            <div
                class="mt-1 w-48 text-xs leading-snug text-gray-700"
                x-show="open"
                x-cloak
            >
                @foreach ($displayItems as $item)
                    <div class="truncate">
                        {{ $item['qty'] }}× {{ $item['title'] }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
