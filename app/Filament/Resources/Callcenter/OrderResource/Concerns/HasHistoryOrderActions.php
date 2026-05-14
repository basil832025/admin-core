<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Concerns;

use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Models\Shop\ClientAddress;
use App\Services\DeliveryCalculationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

trait HasHistoryOrderActions
{
    public function openHistoryOrderAction(): Action
    {
        return Action::make('openHistoryOrder')
            ->label('Відкрити замовлення')
            ->slideOver()
            ->modalWidth('7xl')
            ->mountUsing(function (Form $form, array $arguments): void {
                $orderId = (int) ($arguments['orderId'] ?? 0);

                $rows = OrderItem::query()
                    ->where('shop_order_id', $orderId)
                    ->with('product:id,title')
                    ->get()
                    ->map(fn (OrderItem $it) => [
                        'item_id' => $it->id,
                        'selected' => false,
                        'title' => $it->product->title ?? ('#' . $it->product_id),
                        'qty' => (int) $it->qty,
                        'price' => (float) $it->unit_price,
                    ])
                    ->values()
                    ->all();

                $form->fill(['rows' => $rows]);
            })
            ->form([
                Section::make('Позиції замовлення')
                    ->schema([
                        Repeater::make('rows')
                            ->label(' ')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columns(12)
                            ->schema([
                                Checkbox::make('selected')->label(' ')->columnSpan(1),
                                TextInput::make('title')->label('Товар')->disabled()->dehydrated(false)->columnSpan(7),
                                TextInput::make('qty')->label('К-сть')->numeric()->minValue(1)->step(1)->columnSpan(2),
                                TextInput::make('price')->label('Ціна')->numeric()->dehydrated(false)->columnSpan(2),
                                Hidden::make('item_id'),
                            ]),
                    ]),
            ])
            ->modalSubmitActionLabel('Додати в поточне замовлення')
            ->action(function (array $data): void {
                $selectedRows = collect($data['rows'] ?? [])
                    ->filter(fn (array $row) => (bool) ($row['selected'] ?? false))
                    ->values();

                if ($selectedRows->isEmpty()) {
                    Notification::make()->warning()->title('Не обрано жодної позиції')->send();

                    return;
                }

                $toAdd = $selectedRows
                    ->map(function (array $row): ?array {
                        $source = OrderItem::find((int) ($row['item_id'] ?? 0));

                        if (! $source) {
                            return null;
                        }

                        return [
                            'product_id' => $source->product_id,
                            'qty' => max(1, (int) ($row['qty'] ?? 1)),
                            'unit_price' => (float) $source->unit_price,
                        ];
                    })
                    ->filter()
                    ->values();

                if ($toAdd->isEmpty()) {
                    Notification::make()->warning()->title('Немає валідних позицій для додавання')->send();

                    return;
                }

                if (isset($this->record) && $this->record?->exists) {
                    $this->record->items()->createMany($toAdd->all());

                    Notification::make()->success()->title('Позиції додано')->send();

                    $this->redirect(
                        \App\Filament\Resources\Callcenter\OrderResource::getUrl('edit', ['record' => $this->record]),
                        navigate: true,
                    );

                    return;
                }

                $formState = $this->form->getState();
                $items = collect($formState['items'] ?? [])
                    ->map(fn ($item) => is_object($item) ? (array) $item : $item)
                    ->values();

                foreach ($toAdd as $row) {
                    $items->push($row);
                }

                $formState['items'] = $items->all();
                $this->form->fill($formState);

                Notification::make()->success()->title('Позиції додано')->send();
            });
    }

    public function useAddressFromHistory(int $historyOrderId, ?int $historyAddressId = null): void
    {
        try {
            \Log::info('Callcenter history: useAddressFromHistory called', [
                'history_order_id' => $historyOrderId,
                'history_address_id' => $historyAddressId,
                'page' => static::class,
            ]);

            $source = Order::query()
                ->with('clientAddress')
                ->find($historyOrderId);

            if (! $source) {
                \Log::warning('Callcenter history: source order not found', ['history_order_id' => $historyOrderId]);

                return;
            }

            $address = $this->extractAddressFromOrder($source);

            if ($address === []) {
                Notification::make()->warning()->title('У вибраному замовленні немає адреси')->send();

                return;
            }

            $current = $this->getCurrentFormState();
            $mergedAddress = array_merge((array) ($current['address'] ?? []), $address);

        // Базово показываем блок адреса.
        if (property_exists($this, 'data')) {
            data_set($this->data, 'self_pickup', false);
        }

            $selectedAddressId = (int) ($historyAddressId ?: ($source->client_address_id ?? 0));

            if ($selectedAddressId > 0) {
                $payload = [
                    'selected_address_id' => (string) $selectedAddressId,
                    'client_address_id' => $selectedAddressId,
                    'address' => $mergedAddress,
                    'delivery_coords_trigger' => 'history_select_' . $historyOrderId . '_' . time(),
                ];

                $actualState = property_exists($this, 'data') && is_array($this->data)
                    ? array_replace_recursive($this->data, $payload)
                    : array_replace_recursive($current, $payload);

                $shippingPrice = $this->calculateShippingForState($actualState, $mergedAddress);
                $payload['shipping_price'] = $shippingPrice;
                $payload['delivery_price_auto'] = 'history_auto_' . $historyOrderId . '_' . time();

                if (property_exists($this, 'data') && is_array($this->data)) {
                    $this->data = array_replace_recursive($this->data, $payload);
                }

                $this->form->fill(array_replace_recursive($current, $payload));

                $this->dispatch('callcenter-history-address-applied',
                    selectedAddressId: (string) $selectedAddressId,
                    address: $mergedAddress,
                    shippingPrice: $shippingPrice,
                    componentId: method_exists($this, 'getId') ? $this->getId() : null,
                );

                \Log::info('Callcenter history: address applied from saved address', [
                    'selected_address_id' => $selectedAddressId,
                    'shipping_price' => $shippingPrice,
                    'street' => $mergedAddress['street'] ?? null,
                    'house' => $mergedAddress['house'] ?? null,
                ]);

                Notification::make()->success()->title('Адресу підставлено з історії')->send();

                return;
            }

            $payload = [
                'selected_address_id' => '-1',
                'client_address_id' => null,
                'address' => $mergedAddress,
                'delivery_coords_trigger' => 'history_' . $historyOrderId . '_' . time(),
            ];

            if (property_exists($this, 'data') && is_array($this->data)) {
                $this->data = array_replace_recursive($this->data, $payload);
            }

            $this->form->fill(array_replace_recursive($current, $payload));

        // После перерисовки формы повторно подставляем address,
        // т.к. selected_address_id='-1' может очистить поля в afterStateUpdated.
            if (property_exists($this, 'data') && is_array($this->data)) {
                data_set($this->data, 'address', $mergedAddress);
                data_set($this->data, 'selected_address_id', '-1');
                data_set($this->data, 'delivery_coords_trigger', 'history_' . $historyOrderId . '_' . time() . '_2');
            }

            $actualState = property_exists($this, 'data') && is_array($this->data)
                ? $this->data
                : $this->getCurrentFormState();

            $shippingPrice = $this->calculateShippingForState($actualState, $mergedAddress);

            if (property_exists($this, 'data') && is_array($this->data)) {
                data_set($this->data, 'shipping_price', $shippingPrice);
            }

            $this->form->fill(array_replace_recursive($this->getCurrentFormState(), [
                'selected_address_id' => '-1',
                'address' => $mergedAddress,
                'shipping_price' => $shippingPrice,
                'delivery_price_auto' => 'history_auto_' . $historyOrderId . '_' . time(),
            ]));

            $this->dispatch('callcenter-history-address-applied',
                selectedAddressId: '-1',
                address: $mergedAddress,
                shippingPrice: $shippingPrice,
                componentId: method_exists($this, 'getId') ? $this->getId() : null,
            );

            \Log::info('Callcenter history: address applied as new', [
                'shipping_price' => $shippingPrice,
                'street' => $mergedAddress['street'] ?? null,
                'house' => $mergedAddress['house'] ?? null,
            ]);

            Notification::make()->success()->title('Адресу підставлено з історії')->send();
        } catch (\Throwable $e) {
            \Log::error('Callcenter history: failed to apply address', [
                'history_order_id' => $historyOrderId,
                'history_address_id' => $historyAddressId,
                'error' => $e->getMessage(),
            ]);

            Notification::make()->danger()->title('Не вдалося підставити адресу')->send();
        }
    }

    protected function calculateShippingForState(array $state, array $address): float
    {
        $selfPickup = (bool) ($state['self_pickup'] ?? false);

        if ($selfPickup) {
            return 0.0;
        }

        $items = collect($state['items'] ?? [])
            ->map(fn ($item) => is_object($item) ? (array) $item : (array) $item);

        $baseTotal = (float) $items->sum(function (array $item): float {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['unit_price'] ?? 0);
            $mods = collect($item['modifiers'] ?? [])->map(fn ($m) => is_object($m) ? (array) $m : (array) $m);
            $modsSum = (float) $mods->sum(fn (array $m) => (float) ($m['price_modifier'] ?? 0));

            return $qty * ($price + $modsSum);
        });

        $lat = $address['latitude'] ?? null;
        $lng = $address['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return (float) ($state['shipping_price'] ?? 0);
        }

        $tempOrder = new Order();
        $tempOrder->address = $address;
        $tempOrder->self_pickup = false;

        $delivery = app(DeliveryCalculationService::class)->calculateDelivery($tempOrder, $baseTotal);

        return (float) ($delivery['price'] ?? 0);
    }

    protected function getCurrentFormState(): array
    {
        $raw = $this->form->getRawState();

        if ($raw instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $raw->toArray();
        }

        return is_array($raw) ? $raw : [];
    }

    protected function extractAddressFromOrder(Order $order): array
    {
        if (array_key_exists('address', $order->getAttributes())) {
            $raw = $order->getAttribute('address');

            if (is_array($raw) && $raw !== []) {
                return $raw;
            }
        }

        /** @var ClientAddress|null $clientAddress */
        $clientAddress = $order->clientAddress;

        if (! $clientAddress) {
            return [];
        }

        return [
            'city' => $clientAddress->city,
            'street' => $clientAddress->street,
            'house' => $clientAddress->house,
            'apartment' => $clientAddress->apartment,
            'intercom' => $clientAddress->intercom,
            'floor' => $clientAddress->floor,
            'entrance' => $clientAddress->entrance,
            'note' => $clientAddress->note,
            'is_private_house' => (bool) $clientAddress->is_private_house,
            'type' => $clientAddress->type,
            'latitude' => $clientAddress->latitude,
            'longitude' => $clientAddress->longitude,
            'street_place_id' => $clientAddress->street_place_id,
            'formatted_address' => $clientAddress->formatted_address,
        ];
    }
}
