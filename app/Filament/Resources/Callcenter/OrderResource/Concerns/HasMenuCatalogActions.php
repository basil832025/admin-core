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
        $quantity = 1;
        $unitPriceOverride = null;
        $productSnapshot = null;
        $itemMeta = [];

        if (is_string($productId) && str_starts_with($productId, TimeshopCatalogService::SOURCE_ID . ':')) {
            $timeshopProductId = Str::after($productId, TimeshopCatalogService::SOURCE_ID . ':');
            $product = app(TimeshopCatalogService::class)->ensureLocalProduct($timeshopProductId);
        } elseif (is_string($productId) && str_starts_with($productId, 'measure:')) {
            $parts = explode(':', $productId);
            $localProductId = (int) ($parts[1] ?? 0);
            $selectedQuantity = (float) ($parts[2] ?? 0);
            $product = Product::query()->with('unit')->select(['id', 'title', 'price', 'unit_id', 'price_unit_quantity', 'in_stock'])->find($localProductId);

            if ($product && $selectedQuantity > 0) {
                $unitQuantity = max(0.001, (float) ($product->price_unit_quantity ?? 1));
                $quantity = 1;
                $unitPriceOverride = round((((float) ($product->price ?? 0)) / $unitQuantity) * $selectedQuantity, 2);
                $unitCode = (string) ($product->unit?->code ?? '');
                $unitLabel = static::unitShortLabel($product);
                $measureLabel = static::formatMenuQuantity($selectedQuantity) . ' ' . $unitLabel;
                $productSnapshot = [
                    'menu_measure_quantity' => $selectedQuantity,
                    'menu_measure_unit' => $unitCode,
                    'menu_measure_label' => $measureLabel,
                ];
                $itemMeta = [
                    'volume' => $measureLabel,
                    'cart_label' => $measureLabel,
                ];
            }
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
            'qty' => $quantity,
            'unit_price' => $unitPriceOverride ?? (float) ($product->price ?? 0),
        ];

        if ($productSnapshot !== null) {
            $newItem['product_snapshot'] = $productSnapshot;
        }

        if ($itemMeta !== []) {
            $newItem['meta'] = $itemMeta;
        }

        // Edit mode: пишем сразу в БД заказа.
        if (isset($this->record) && $this->record?->exists) {
            $existingItem = null;
            $affectedItem = null;

            if ($itemMeta !== []) {
                $existingItem = $this->record->items()
                    ->where('product_id', $product->id)
                    ->get()
                    ->first(fn ($item): bool => static::sameMenuMeasureMeta((array) ($item->meta ?? []), $itemMeta));
            }

            if ($existingItem) {
                $existingItem->qty = max(1, (int) $existingItem->qty) + $quantity;
                $existingItem->unit_price = $unitPriceOverride ?? (float) ($product->price ?? 0);
                $existingItem->product_snapshot = $productSnapshot;
                $existingItem->meta = $itemMeta;
                $existingItem->save();
                $affectedItem = $existingItem->fresh();
            } else {
                $affectedItem = $this->record->items()->create([
                    'product_id' => $product->id,
                    'qty' => $quantity,
                    'unit_price' => $unitPriceOverride ?? (float) ($product->price ?? 0),
                    'product_snapshot' => $productSnapshot,
                    'meta' => $itemMeta !== [] ? $itemMeta : null,
                ]);
            }

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
            $newItem['id'] = $affectedItem?->id;
            $newItem['qty'] = (int) ($affectedItem?->qty ?? $quantity);
            $newItem['unit_price'] = (float) ($affectedItem?->unit_price ?? ($unitPriceOverride ?? $product->price ?? 0));
            $newItem['product_snapshot'] = $affectedItem?->product_snapshot ?? $productSnapshot;
            $newItem['meta'] = $affectedItem?->meta ?? $itemMeta;

            if ($itemMeta !== []) {
                $affectedItemId = (int) ($affectedItem?->id ?? 0);
                $index = $items->search(fn (array $item): bool => $affectedItemId > 0
                    ? (int) ($item['id'] ?? 0) === $affectedItemId
                    : ((int) ($item['product_id'] ?? 0) === (int) $product->id
                        && static::sameMenuMeasureMeta((array) ($item['meta'] ?? []), $itemMeta)));

                if ($index !== false) {
                    $row = $items->get($index);
                    $row['id'] = $newItem['id'];
                    $row['qty'] = $newItem['qty'];
                    $row['unit_price'] = $newItem['unit_price'];
                    $row['product_snapshot'] = $newItem['product_snapshot'];
                    $row['meta'] = $newItem['meta'];
                    $items->put($index, $row);
                } else {
                    $items->push($newItem);
                }
            } else {
                $items->push($newItem);
            }
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

        if ($itemMeta !== []) {
            $index = $items->search(fn (array $item): bool => (int) ($item['product_id'] ?? 0) === (int) $product->id
                && static::sameMenuMeasureMeta((array) ($item['meta'] ?? []), $itemMeta));

            if ($index !== false) {
                $row = $items->get($index);
                $row['qty'] = max(1, (int) ($row['qty'] ?? 0)) + $quantity;
                $row['unit_price'] = $unitPriceOverride ?? (float) ($product->price ?? 0);
                $row['product_snapshot'] = $productSnapshot;
                $row['meta'] = $itemMeta;
                $items->put($index, $row);
            } else {
                $items->push($newItem);
            }
        } else {
            $items->push($newItem);
        }

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

        $product = Product::query()
            ->with('unit')
            ->select(['id', 'parent_id', 'unit_id', 'price_unit_quantity'])
            ->find($productId);

        if ($product?->unit && $product->unit->code !== 'sht') {
            $quantity = (float) ($product->price_unit_quantity ?? 1);

            return $cache[$productId] = static::formatMenuQuantity($quantity) . ' ' . static::unitShortLabel($product);
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

    public static function menuUnitOptionVariants(Product $product): array
    {
        if (! $product->relationLoaded('unit')) {
            $product->load('unit');
        }

        $unitCode = (string) ($product->unit?->code ?? '');
        $quantities = static::menuUnitOptionQuantities($unitCode);

        if ($quantities === []) {
            return [];
        }

        $baseQuantity = max(0.001, (float) ($product->price_unit_quantity ?? 1));
        $basePrice = (float) ($product->price ?? 0);

        if ($basePrice <= 0) {
            return [];
        }

        $oldBasePrice = (float) ($product->old_price ?? 0);
        $unitLabel = static::unitShortLabel($product);

        return collect($quantities)
            ->map(function (float $quantity) use ($product, $baseQuantity, $basePrice, $oldBasePrice, $unitLabel): array {
                $quantityLabel = static::formatMenuQuantity($quantity);
                $price = round(($basePrice / $baseQuantity) * $quantity, 2);
                $oldPrice = $oldBasePrice > $basePrice
                    ? round(($oldBasePrice / $baseQuantity) * $quantity, 2)
                    : 0;

                return [
                    'id' => 'measure:' . $product->id . ':' . $quantityLabel,
                    'title' => $quantityLabel . ' ' . $unitLabel,
                    'price' => $price,
                    'old_price' => $oldPrice,
                    'unit' => $quantityLabel . ' ' . $unitLabel,
                ];
            })
            ->all();
    }

    protected static function menuUnitOptionQuantities(string $unitCode): array
    {
        $raw = (string) config("services.callcenter.order_menu_unit_options.{$unitCode}", '');

        return collect(explode(',', $raw))
            ->map(fn (string $value): float => (float) trim(str_replace(',', '.', $value)))
            ->filter(fn (float $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected static function sameMenuMeasureMeta(array $left, array $right): bool
    {
        return static::menuMeasureKey($left) === static::menuMeasureKey($right);
    }

    protected static function menuMeasureKey(array $meta): string
    {
        $volume = trim((string) ($meta['volume'] ?? ''));

        if ($volume === '' && ! empty($meta['cart_label'])) {
            preg_match('/\d+(?:[.,]\d+)?\s*(?:мл|ml)/iu', (string) $meta['cart_label'], $matches);
            $volume = trim((string) ($matches[0] ?? ''));
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', $volume));
    }

    protected static function unitShortLabel(Product $product): string
    {
        $locale = app()->getLocale();

        return (string) (
            $product->unit?->getTranslation('short_name', $locale, false)
            ?: $product->unit?->getTranslation('name', $locale, false)
            ?: $product->unit?->code
            ?: ''
        );
    }

    protected static function formatMenuQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') ?: '1';
    }
}
