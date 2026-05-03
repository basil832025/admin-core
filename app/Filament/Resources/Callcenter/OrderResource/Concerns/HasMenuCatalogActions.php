<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Concerns;

use App\Models\Shop\Product;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Shop\Order as ShopOrder;
use App\Services\OrderPricing;
use App\Services\DeliveryCalculationService;
use Illuminate\Support\Facades\Log;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasMenuCatalogActions
{
    public function openMenuCatalogAction(): Action
    {
        return Action::make('menuCatalog')
            ->label('Меню')
            ->color('gray')
            ->icon('heroicon-m-squares-2x2')
            ->extraAttributes([
                'data-hotkey' => 'cc-menu',
                'data-hotkey-label' => 'Alt+M',
            ])
            ->slideOver()
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрити')
            ->modalContent(fn () => view('filament.callcenter.menu-catalog-slide-over', [
                'componentId' => method_exists($this, 'getId') ? $this->getId() : null,
                'fetchUrl' => route('admin.callcenter.menu-catalog', absolute: false),
                'defaultSourceId' => (string) ((isset($this->record) && $this->record?->exists)
                    ? ((int) ($this->record->source_id ?? 0))
                    : ((int) data_get($this->getCurrentFormStateForMenu(), 'source_id', 0))),
            ]));
    }

    public function addMenuProductToOrder(int $productId, int $sourceId = 0): void
    {
        $product = Product::query()->select(['id', 'price', 'in_stock'])->find($productId);

        if (! $product || ! $product->in_stock) {
            Notification::make()->warning()->title('Товар недоступний')->send();

            return;
        }

        $state = $this->getCurrentFormStateForMenu();
        $items = collect($state['items'] ?? [])
            ->map(fn ($item) => is_object($item) ? (array) $item : (array) $item)
            ->values();

        $newItem = [
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => (float) ($product->price ?? 0),
        ];

        // Edit mode: пишем сразу в БД заказа.
        if (isset($this->record) && $this->record?->exists) {
            $this->record->items()->create([
                'product_id' => $product->id,
                'qty' => 1,
                'unit_price' => (float) ($product->price ?? 0),
            ]);

            app(OrderPricing::class)->recalc($this->record);
            $this->record->recalculateTotalPrice();

            // И сразу отражаем в текущем состоянии формы, чтобы не закрывать меню.
            $items->push($newItem);
            $state['items'] = $items->all();

            $shippingPrice = $this->calculateShippingForMenuState($state);
            $state['shipping_price'] = $shippingPrice;
            $state['delivery_price_auto'] = 'menu_add_' . microtime(true);
            $state['delivery_coords_trigger'] = 'menu_coords_' . microtime(true);

            if (property_exists($this, 'data') && is_array($this->data)) {
                data_set($this->data, 'items', $state['items']);
                data_set($this->data, 'shipping_price', $shippingPrice);
                data_set($this->data, 'delivery_price_auto', $state['delivery_price_auto']);
                data_set($this->data, 'delivery_coords_trigger', $state['delivery_coords_trigger']);
                $state = array_replace_recursive($state, $this->data);
            }

            $this->form->fill($state);
            $this->dispatch('$refresh');

            $this->record->shipping_price = $shippingPrice;
            $this->record->saveQuietly();
            $this->record->recalculateTotalPrice();

            Notification::make()->success()->title('Товар додано')->send();

            return;
        }

        $items->push($newItem);

        $state['items'] = $items->all();

        if ($sourceId > 0) {
            $state['source_id'] = $sourceId;
        }

        $shippingPrice = $this->calculateShippingForMenuState($state);
        $state['shipping_price'] = $shippingPrice;
        $state['delivery_price_auto'] = 'menu_add_' . microtime(true);
        $state['delivery_coords_trigger'] = 'menu_coords_' . microtime(true);

        if (property_exists($this, 'data') && is_array($this->data)) {
            data_set($this->data, 'items', $state['items']);
            if (array_key_exists('source_id', $state)) {
                data_set($this->data, 'source_id', $state['source_id']);
            }
            data_set($this->data, 'shipping_price', $shippingPrice);
            data_set($this->data, 'delivery_price_auto', $state['delivery_price_auto']);
            data_set($this->data, 'delivery_coords_trigger', $state['delivery_coords_trigger']);
            $state = array_replace_recursive($state, $this->data);
        }

        $this->form->fill($state);
        $this->dispatch('$refresh');

        Log::info('Callcenter menu: item added to create form', [
            'product_id' => $product->id,
            'items_count' => count($state['items'] ?? []),
        ]);

        Notification::make()->success()->title('Товар додано')->send();
    }

    protected function calculateShippingForMenuState(array $state): float
    {
        $selfPickup = (bool) ($state['self_pickup'] ?? false);

        if ($selfPickup) {
            return 0.0;
        }

        $address = (array) ($state['address'] ?? []);
        $lat = $address['latitude'] ?? null;
        $lng = $address['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return (float) ($state['shipping_price'] ?? 0);
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

        $tempOrder = new ShopOrder();
        $tempOrder->address = $address;
        $tempOrder->self_pickup = false;

        $delivery = app(DeliveryCalculationService::class)->calculateDelivery($tempOrder, $baseTotal);

        return (float) ($delivery['price'] ?? 0);
    }

    protected function getCurrentFormStateForMenu(): array
    {
        $raw = $this->form->getRawState();

        if ($raw instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $raw->toArray();
        }

        return is_array($raw) ? $raw : [];
    }

    public static function resolveMenuUnitLabel(int $productId): string
    {
        static $cache = [];

        if (isset($cache[$productId])) {
            return $cache[$productId];
        }

        $priority = ['rozmir-pirogiv', 'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];

        $rows = ProductCharacteristicValue::query()
            ->with([
                'characteristic:id,slug',
                'characteristicValue:id,characteristic_id,value',
                'characteristicValue.characteristic:id,slug',
            ])
            ->where('product_id', $productId)
            ->get();

        foreach ($priority as $slug) {
            $match = $rows->first(function (ProductCharacteristicValue $row) use ($slug): bool {
                $rowSlug = $row->characteristic?->slug
                    ?? $row->characteristicValue?->characteristic?->slug;

                return $rowSlug === $slug;
            });

            if (! $match) {
                continue;
            }

            $value = trim((string) ($match->value_text ?? ''));

            if ($value === '' && $match->value_number !== null) {
                $value = (string) $match->value_number;
            }

            if ($value === '' && $match->characteristicValue) {
                $label = trim((string) ($match->characteristicValue->label ?? ''));
                if ($label !== '') {
                    $value = $label;
                } else {
                    $raw = $match->characteristicValue->getRawOriginal('value');
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $locale = app()->getLocale();
                            $value = trim((string) (
                                $decoded[$locale]
                                ?? $decoded['uk']
                                ?? $decoded['ru']
                                ?? $decoded['en']
                                ?? (count($decoded) ? reset($decoded) : '')
                            ));
                        } else {
                            $value = trim($raw, " \t\n\r\0\x0B\"");
                        }
                    }
                }
            }

            if ($value !== '') {
                return $cache[$productId] = $value;
            }
        }

        return $cache[$productId] = '';
    }
}
