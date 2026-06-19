<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Concerns;

use App\Models\Shop\Product;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Shop\Order as ShopOrder;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Services\OrderPricing;
use App\Services\DeliveryCalculationService;
use App\Services\Callcenter\TimeshopCatalogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait HasMenuCatalogActions
{
    public function openMenuCatalogAction(): Action
    {
        return Action::make('menuCatalog')
            ->label(__('callcenter.actions.menu'))
            ->color('gray')
            ->icon('heroicon-m-squares-2x2')
            ->extraAttributes([
                'data-hotkey' => 'cc-menu',
                'data-hotkey-label' => 'Alt+M',
            ])
            ->slideOver()
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('order.actions.cancel'))
            ->modalContent(fn () => view('filament.callcenter.menu-catalog-slide-over', [
                'componentId' => method_exists($this, 'getId') ? $this->getId() : null,
                'fetchUrl' => route('admin.callcenter.menu-catalog', absolute: false),
                'defaultSourceId' => $this->resolveDefaultMenuSourceId(),
            ]));
    }

    protected function resolveDefaultMenuSourceId(): string
    {
        if ((string) config('services.callcenter.order_menu_source', 'main') === TimeshopCatalogService::SOURCE_ID) {
            return TimeshopCatalogService::SOURCE_ID;
        }

        $currentSourceId = (isset($this->record) && $this->record?->exists)
            ? (int) ($this->record->source_id ?? 0)
            : (int) data_get($this->getCurrentFormStateForMenu(), 'source_id', 0);

        if ($currentSourceId > 0) {
            return (string) $currentSourceId;
        }

        return '0';
    }

    public function addMenuProductToOrder(string|int $productId, string|int $sourceId = 0): void
    {
        $sourceIdValue = is_numeric($sourceId) ? (int) $sourceId : 0;

        if (is_string($productId) && str_starts_with($productId, TimeshopCatalogService::SOURCE_ID . ':')) {
            $timeshopProductId = Str::after($productId, TimeshopCatalogService::SOURCE_ID . ':');
            $product = app(TimeshopCatalogService::class)->ensureLocalProduct($timeshopProductId);
        } else {
            $product = Product::query()->select(['id', 'price', 'in_stock'])->find((int) $productId);
        }

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

            $record = $this->record->fresh();
            $pricing = app(OrderPricing::class);

            $selectedTimeId = (int) ($state['ui_time_discount_id'] ?? 0);
            $selectedFixedId = (int) ($state['ui_fixed_discount_id'] ?? 0);

            if ($selectedTimeId > 0) {
                $pricing->applyTimeExclusive($record, $selectedTimeId, 'single');
            } elseif ($selectedFixedId > 0) {
                $pricing->applyFixedExclusive($record, $selectedFixedId, 'single');
            } else {
                $timeAdj = $record->adjustments()
                    ->where('type', 'time')
                    ->whereNull('shop_order_item_id')
                    ->latest('id')
                    ->first();

                if ($timeAdj) {
                    $timeId = (int) (data_get($timeAdj->meta, 'id') ?? data_get($timeAdj->meta, 'time_discount_id') ?? 0);
                    if ($timeId > 0) {
                        $pricing->applyTimeExclusive($record, $timeId, 'single');
                    } else {
                        $pricing->recalc($record);
                    }
                } else {
                    $fixedAdj = $record->adjustments()
                        ->where('type', 'fixed')
                        ->whereNull('shop_order_item_id')
                        ->latest('id')
                        ->first();

                    if ($fixedAdj) {
                        $fixedId = (int) (data_get($fixedAdj->meta, 'id') ?? data_get($fixedAdj->meta, 'fixed_discount_id') ?? 0);
                        if ($fixedId > 0) {
                            $pricing->applyFixedExclusive($record, $fixedId, 'single');
                        } else {
                            $pricing->recalc($record);
                        }
                    } else {
                        $pricing->recalc($record);
                    }
                }
            }

            $record = $record->fresh();
            $record->recalculateTotalPrice();
            $this->record = $record->fresh();

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

            $this->record->shipping_price = $shippingPrice;
            $this->record->saveQuietly();
            $this->record->recalculateTotalPrice();

            Notification::make()->success()->title('Товар додано')->send();

            return;
        }

        $items->push($newItem);

        $state['items'] = $items->all();

        if ($sourceIdValue > 0) {
            $state['source_id'] = $sourceIdValue;
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

        $recordId = (int) ($state['id'] ?? 0);
        $record = $recordId > 0 ? ShopOrder::query()->find($recordId) : null;
        $baseTotal = OrderResource::calcDeliveryBaseForState($state, $record);

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
