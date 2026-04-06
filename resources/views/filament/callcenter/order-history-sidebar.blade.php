@php
    /** @var \Illuminate\Support\Collection $orders */
    $orders = $orders ?? collect();
@endphp

@if($orders->isEmpty())
    <div class="text-sm text-gray-500">
        Історія замовлень недоступна. Оберіть клієнта.
    </div>
@else
    <div class="space-y-3">
        @foreach($orders as $order)
            @php
                $address = [];

                if (array_key_exists('address', $order->getAttributes()) && is_array($order->address)) {
                    $address = $order->address;
                } elseif ($order->clientAddress) {
                    $address = [
                        'street' => $order->clientAddress->street,
                        'house' => $order->clientAddress->house,
                        'apartment' => $order->clientAddress->apartment,
                    ];
                }

                $addressLine = trim(implode(', ', array_filter([
                    $address['street'] ?? null,
                    $address['house'] ?? null,
                    $address['apartment'] ?? null ? 'кв. ' . $address['apartment'] : null,
                ])));
            @endphp

            <div class="rounded-lg border border-gray-200 p-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-sm font-semibold">#{{ $order->number }}</div>
                    <div class="text-xs text-gray-500">{{ ($order->placedAt() ?? $order->created_at)?->format('d.m.Y H:i') }}</div>
                </div>

                <div class="mt-1 text-xs text-gray-500">
                    {{ $addressLine !== '' ? $addressLine : 'Адреса відсутня' }}
                </div>

                <div class="mt-2 flex gap-2">
                    <button
                        type="button"
                        class="fi-btn fi-size-xs inline-grid gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold ring-1 ring-gray-300 text-gray-700 hover:bg-gray-50"
                        wire:key="history-use-address-{{ $order->id }}"
                        wire:click.prevent.stop="useAddressFromHistory({{ $order->id }}, {{ (int) ($order->client_address_id ?? 0) }})"
                    >
                        Використати адресу
                    </button>

                    <button
                        type="button"
                        class="fi-btn fi-size-xs inline-grid gap-1 rounded-lg px-2 py-1.5 text-xs font-semibold bg-blue-600 text-white hover:bg-blue-500"
                        wire:click.prevent.stop="mountAction('openHistoryOrder', { orderId: {{ $order->id }} })"
                    >
                        Відкрити замовлення
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
