<x-filament::page>
    <div wire:poll.5s class="space-y-3">

        {{-- Шапка таблицы --}}
        <div class="rounded-xl border bg-white">
            <div class="grid grid-cols-12 items-center px-4 py-3 text-sm font-medium text-gray-700 bg-gray-50 rounded-t-xl">
                <div class="col-span-3">{{ __('№ заказа') }}</div>
                <div class="col-span-3">{{ __('Время заказа') }}</div>
                <div class="col-span-2">{{ __('Поскорее') }}</div>
                <div class="col-span-2">{{ __('Тип') }}</div>
                <div class="col-span-2">{{ __('Этап') }}</div>
            </div>
        </div>

        {{-- Строки --}}
        @foreach ($this->getRecords() as $ticket)
            @php
                $stage = $ticket->stage instanceof \App\Enums\OrderStatus
                    ? $ticket->stage
                    : \App\Enums\OrderStatus::from($ticket->stage);

                $order  = $ticket->order;
                $dtText = \Illuminate\Support\Arr::join(
                    array_filter([$order?->date_order, $order?->time_order]),
                    ' '
                );
            @endphp

            <div class="rounded-xl border bg-white overflow-hidden">
                {{-- строка данных (как в таблице) --}}
                <div class="grid grid-cols-12 items-center px-4 py-3 text-sm">
                    <div class="col-span-3 font-semibold">
                        {{ $order?->number ?: '—' }}
                    </div>

                    <div class="col-span-3 text-gray-700">
                        {{ $dtText ?: '—' }}
                    </div>

                    <div class="col-span-2">
                        @if($ticket->urgent)
                            <x-filament::badge color="danger">{{ __('Срочно') }}</x-filament::badge>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>

                    <div class="col-span-2">
                        <x-filament::badge :color="$order?->self_pickup ? 'success' : 'info'">
                            {{ $order?->self_pickup ? __('Самовывоз') : __('Доставка') }}
                        </x-filament::badge>
                    </div>

                    <div class="col-span-2">
                        <x-filament::badge :color="$stage->getColor()">
                            {{ $stage->getLabel() }}
                        </x-filament::badge>  <x-filament::badge :color="$stage->getColor()">
                            {{ $stage->getLabel() }}
                        </x-filament::badge>
                    </div>
                </div>

                {{-- подстрочная мини-таблица позиций --}}
                @php $items = $order?->items ?? collect(); @endphp
                @if ($items->isNotEmpty())
                    <div class="px-4 pb-4">
                        <div class="rounded-lg border bg-gray-50">
                            <table class="w-full text-sm">
                                <thead class="text-gray-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">{{ __('Наименование') }}</th>
                                    <th class="px-3 py-2 text-right w-16">{{ __('К-во') }}</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                @foreach ($items as $it)
                                    @php
                                        $name = $it->product?->display_name
                                            ?: data_get($it->meta, 'title')
                                            ?: data_get($it->meta, 'product_title')
                                            ?: '—';
                                        $variant = data_get($it->meta, 'variant');
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2">
                                            {{ $name }}
                                            @if($variant)
                                                <div class="text-xs text-gray-500">{{ $variant }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">× {{ (int) $it->qty }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach

    </div>
</x-filament::page>
