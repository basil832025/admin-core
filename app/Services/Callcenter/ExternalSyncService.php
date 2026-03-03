<?php

namespace App\Services\Callcenter;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Models\Callcenter\Source;
use App\Models\Callcenter\SourceCategory;
use App\Models\Callcenter\SourceClient;
use App\Models\Callcenter\SourceOrder;
use App\Models\Callcenter\SourceProduct;
use App\Models\Callcenter\SyncRun;
use App\Models\Shop\Client;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Models\Shop\Product;
use App\Models\Shop\ProductCategory;
use App\Services\OrderPricing;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExternalSyncService
{
    /** @var array<string, bool> */
    protected array $catalogRefreshState = [];

    public function syncCatalogFromAllSources(): array
    {
        $sources = $this->activeSources();
        $total = [
            'sources' => $sources->count(),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $stats = $this->syncCatalogForSource($source);
                $total['processed'] += (int) ($stats['processed'] ?? 0);
                $total['created'] += (int) ($stats['created'] ?? 0);
                $total['updated'] += (int) ($stats['updated'] ?? 0);
            } catch (\Throwable $e) {
                $total['failed']++;
                $total['errors'][] = $source->name . ': ' . $e->getMessage();
            }
        }

        return $total;
    }

    public function syncOrdersFromAllSources(int $limit = 50): array
    {
        $sources = $this->activeSources();
        $total = [
            'sources' => $sources->count(),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $stats = $this->syncOrdersForSource($source, $limit);
                $total['processed'] += (int) ($stats['processed'] ?? 0);
                $total['created'] += (int) ($stats['created'] ?? 0);
                $total['updated'] += (int) ($stats['updated'] ?? 0);
                $total['failed'] += (int) ($stats['failed'] ?? 0);
            } catch (\Throwable $e) {
                $total['failed']++;
                $total['errors'][] = $source->name . ': ' . $e->getMessage();
            }
        }

        return $total;
    }

    public function syncClientsFromAllSources(int $limit = 200): array
    {
        $sources = $this->activeSources();
        $total = [
            'sources' => $sources->count(),
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($sources as $source) {
            try {
                $stats = $this->syncClientsForSource($source, $limit);
                $total['processed'] += (int) ($stats['processed'] ?? 0);
                $total['created'] += (int) ($stats['created'] ?? 0);
                $total['updated'] += (int) ($stats['updated'] ?? 0);
                $total['failed'] += (int) ($stats['failed'] ?? 0);
            } catch (\Throwable $e) {
                $total['failed']++;
                $total['errors'][] = $source->name . ': ' . $e->getMessage();
            }
        }

        return $total;
    }

    public function syncClientsForSource(Source $source, int $limit = 200): array
    {
        $orders = $this->fetchSourceOrders($source, $limit);

        $processed = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        foreach ($orders as $orderPayload) {
            $processed++;

            try {
                $before = SourceClient::query()
                    ->where('source_id', $source->id)
                    ->where('external_phone', $this->normalizePhone((string) Arr::get($orderPayload, 'phone', '')))
                    ->exists();

                $localClient = $this->ensureLocalClient($source, $orderPayload);
                $this->ensureClientAddress($localClient, $orderPayload);

                $before ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        SyncRun::create([
            'source_id' => $source->id,
            'type' => 'clients',
            'status' => $failed > 0 ? 'partial' : 'success',
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ]);

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    public function repairImportedOrders(?int $sourceId = null, ?string $externalOrderId = null): array
    {
        $query = SourceOrder::query()->with(['source', 'localOrder.items.modifiers', 'localOrder.adjustments']);

        if ($sourceId !== null) {
            $query->where('source_id', $sourceId);
        }

        if ($externalOrderId !== null && $externalOrderId !== '') {
            $query->where('external_id', $externalOrderId);
        }

        $rows = $query->orderBy('id')->get();

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($rows as $row) {
            $stats['processed']++;

            try {
                if (! $row->source || ! $row->localOrder) {
                    $stats['failed']++;
                    $stats['errors'][] = "Missing source/local order for source_order_id={$row->id}";
                    continue;
                }

                $this->synchronizeOrderTotalsFromPayload($row->localOrder, (array) ($row->payload ?? []), $row->source);
                $row->forceFill([
                    'sync_status' => 'imported',
                    'last_error' => null,
                    'synced_at' => now(),
                ])->saveQuietly();

                $stats['updated']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $stats['errors'][] = "{$row->source_id}:{$row->external_id} {$e->getMessage()}";

                $row->forceFill([
                    'sync_status' => 'failed',
                    'last_error' => Str::limit($e->getMessage(), 60000, ''),
                    'synced_at' => now(),
                ])->saveQuietly();
            }
        }

        return $stats;
    }

    public function repairImportedOrderByLocalId(int $localOrderId): bool
    {
        $order = Order::query()->find($localOrderId);
        if (! $order || (int) $order->source_id <= 0) {
            return false;
        }

        $row = SourceOrder::query()
            ->with('source')
            ->where('source_id', (int) $order->source_id)
            ->where('local_order_id', $order->id)
            ->latest('id')
            ->first();

        if (! $row || ! $row->source || ! is_array($row->payload)) {
            return false;
        }

        $this->synchronizeOrderTotalsFromPayload($order, $row->payload, $row->source);

        $row->forceFill([
            'sync_status' => 'imported',
            'last_error' => null,
            'synced_at' => now(),
        ])->saveQuietly();

        return true;
    }

    public function syncCatalogForSource(Source $source): array
    {
        $processed = 0;
        $created = 0;
        $updated = 0;

        SourceProduct::query()
            ->where('source_id', $source->id)
            ->where(function ($q): void {
                $q->whereNull('alias')->orWhere('alias', '');
            })
            ->delete();

        $categories = $this->fetchSourceCategories($source);
        foreach ($categories as $categoryPayload) {
            $processed++;
            [, $isNew] = $this->upsertSourceCategory($source, $categoryPayload);
            $isNew ? $created++ : $updated++;
        }

        $this->syncCategoryParents($source);

        $products = $this->fetchSourceProducts($source);
        foreach ($products as $productPayload) {
            $productAlias = trim((string) Arr::get($productPayload, 'alias', ''));
            if ($productAlias === '') {
                continue;
            }

            $variants = $this->extractProductVariants($productPayload);

            foreach ($variants as $variantPayload) {
                $processed++;
                [$sourceProduct, $isNew] = $this->upsertSourceProduct($source, $variantPayload);
                $this->ensureLocalProductFromSourceProduct($source, $sourceProduct, $variantPayload);
                $isNew ? $created++ : $updated++;
            }
        }

        $source->forceFill(['last_catalog_synced_at' => now()])->saveQuietly();

        $run = SyncRun::create([
            'source_id' => $source->id,
            'type' => 'catalog',
            'status' => 'success',
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => 0,
        ]);

        return [
            'run_id' => $run->id,
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => 0,
        ];
    }

    public function syncOrdersForSource(Source $source, int $limit = 50): array
    {
        $processed = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;

        $orders = $this->fetchSourceOrders($source, $limit);

        foreach ($orders as $orderPayload) {
            $processed++;
            $externalOrderId = (string) Arr::get($orderPayload, 'id', '');
            if ($externalOrderId === '') {
                $failed++;
                continue;
            }

            $existingOrder = Order::query()
                ->where('source_id', $source->id)
                ->where('external_order_id', $externalOrderId)
                ->first();

            if ($existingOrder) {
                try {
                    $this->synchronizeOrderTotalsFromPayload($existingOrder, $orderPayload, $source);
                    SourceOrder::updateOrCreate(
                        [
                            'source_id' => $source->id,
                            'external_id' => $externalOrderId,
                        ],
                        [
                            'local_order_id' => $existingOrder->id,
                            'sync_status' => 'imported',
                            'last_error' => null,
                            'payload' => $orderPayload,
                            'synced_at' => now(),
                        ]
                    );
                } catch (\Throwable $e) {
                    $failed++;
                    SourceOrder::updateOrCreate(
                        [
                            'source_id' => $source->id,
                            'external_id' => $externalOrderId,
                        ],
                        [
                            'local_order_id' => $existingOrder->id,
                            'sync_status' => 'failed',
                            'last_error' => Str::limit($e->getMessage(), 60000, ''),
                            'payload' => $orderPayload,
                            'synced_at' => now(),
                        ]
                    );
                }

                $updated++;
                continue;
            }

            try {
                $this->importSingleOrder($source, $orderPayload, $externalOrderId);
                $created++;
            } catch (\Throwable $e) {
                $failed++;

                SourceOrder::updateOrCreate(
                    [
                        'source_id' => $source->id,
                        'external_id' => $externalOrderId,
                    ],
                    [
                        'sync_status' => 'failed',
                        'last_error' => Str::limit($e->getMessage(), 60000, ''),
                        'payload' => $orderPayload,
                        'synced_at' => now(),
                    ]
                );
            }
        }

        $source->forceFill(['last_orders_synced_at' => now()])->saveQuietly();

        $run = SyncRun::create([
            'source_id' => $source->id,
            'type' => 'orders',
            'status' => $failed > 0 ? 'partial' : 'success',
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ]);

        return [
            'run_id' => $run->id,
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    protected function importSingleOrder(Source $source, array $orderPayload, string $externalOrderId): void
    {
        DB::transaction(function () use ($source, $orderPayload, $externalOrderId): void {
            $localClient = $this->ensureLocalClient($source, $orderPayload);
            $clientAddress = $this->ensureClientAddress($localClient, $orderPayload);

            $status = $this->mapStatus((int) Arr::get($orderPayload, 'status_id', 0));
            $payment = $this->mapPayment((int) Arr::get($orderPayload, 'pay_id', 0));

            $createdAt = Carbon::createFromTimestamp((int) Arr::get($orderPayload, 'creation_time', time()));
            $deliveryDate = Arr::get($orderPayload, 'deliveryDate');
            $dateOrder = $deliveryDate ? Carbon::parse((string) $deliveryDate) : $createdAt;

            $localOrder = new Order();
            $localOrder->fill([
                'source_id' => $source->id,
                'external_order_id' => $externalOrderId,
                'clients_id' => $localClient->id,
                'client_address_id' => $clientAddress?->id,
                'currency' => 'UAH',
                'status' => $status,
                'payment' => $payment,
                'notes' => trim((string) Arr::get($orderPayload, 'comment', '')),
                'total_price' => (float) Arr::get($orderPayload, 'total', 0),
                'subtotal' => (float) Arr::get($orderPayload, 'total', 0),
                'grand_total' => (float) Arr::get($orderPayload, 'total', 0),
                'discount_total' => (float) Arr::get($orderPayload, 'discount', 0),
                'shipping_total' => (float) Arr::get($orderPayload, 'delivery', 0),
                'shipping_price' => (float) Arr::get($orderPayload, 'delivery', 0),
                'dat' => $createdAt->toDateString(),
                'time_start' => $createdAt->format('H:i'),
                'time_order' => $dateOrder->format('H:i'),
                'date_order' => $dateOrder->toDateString(),
                'self_pickup' => $this->isSelfPickup($orderPayload),
                'synced_at' => now(),
            ]);
            $localOrder->save();

            if ($createdAt) {
                $localOrder->forceFill([
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->saveQuietly();
            }

            $items = Arr::get($orderPayload, 'items', []);
            $hasUnmatched = false;

            foreach ((array) $items as $itemPayload) {
                $externalProductId = (string) Arr::get($itemPayload, 'product_id', '');
                $localProduct = $this->ensureLocalProductByExternalId($source, $externalProductId, (array) $itemPayload);

                if (! $localProduct) {
                    $hasUnmatched = true;
                }

                $qty = (float) Arr::get($itemPayload, 'count', 1);
                $unitPrice = (float) Arr::get($itemPayload, 'price', Arr::get($itemPayload, 'price_full', 0));

                OrderItem::create([
                    'shop_order_id' => $localOrder->id,
                    'product_id' => $localProduct?->id,
                    'qty' => $qty > 0 ? $qty : 1,
                    'unit_price' => $unitPrice,
                    'currency' => 'UAH',
                    'sku' => $localProduct?->sku,
                    'product_snapshot' => [
                        'external_product_id' => $externalProductId,
                        'title' => Arr::get($itemPayload, 'name'),
                        'weight' => Arr::get($itemPayload, 'weight'),
                        'source' => $source->slug,
                    ],
                    'subtotal' => (float) Arr::get($itemPayload, 'price_full', $unitPrice * max($qty, 1)),
                    'total' => (float) Arr::get($itemPayload, 'price_full', $unitPrice * max($qty, 1)),
                ]);
            }

            $this->synchronizeOrderTotalsFromPayload($localOrder, $orderPayload, $source, $hasUnmatched);

            $sourceOrder = SourceOrder::updateOrCreate(
                [
                    'source_id' => $source->id,
                    'external_id' => $externalOrderId,
                ],
                [
                    'local_order_id' => $localOrder->id,
                    'sync_status' => 'imported',
                    'last_error' => null,
                    'payload' => $orderPayload,
                    'synced_at' => now(),
                ]
            );

            $sourceOrder->items()->delete();

            foreach ((array) Arr::get($orderPayload, 'items', []) as $itemPayload) {
                $externalProductId = (string) Arr::get($itemPayload, 'product_id', '');
                $localProduct = SourceProduct::query()
                    ->where('source_id', $source->id)
                    ->where('external_id', $externalProductId)
                    ->with('localProduct:id')
                    ->first()?->localProduct;

                $sourceOrder->items()->create([
                    'external_item_id' => (string) Arr::get($itemPayload, 'id', ''),
                    'external_product_id' => $externalProductId,
                    'title' => (string) Arr::get($itemPayload, 'name', ''),
                    'qty' => (float) Arr::get($itemPayload, 'count', 1),
                    'unit_price' => (float) Arr::get($itemPayload, 'price', 0),
                    'local_product_id' => $localProduct?->id,
                    'payload' => $itemPayload,
                ]);
            }
        });
    }

    protected function synchronizeOrderTotalsFromPayload(Order $order, array $orderPayload, Source $source, ?bool $hasUnmatchedOverride = null): void
    {
        $shipping = (float) Arr::get($orderPayload, 'delivery', 0);
        $discount = abs((float) Arr::get($orderPayload, 'discount', 0));
        $expectedTotal = (float) Arr::get($orderPayload, 'total', 0);
        $payloadItems = (array) Arr::get($orderPayload, 'items', []);

        $payloadItemsSubtotal = collect($payloadItems)->sum(function ($item): float {
            $qty = (float) Arr::get((array) $item, 'count', 1);
            $qty = $qty > 0 ? $qty : 1;

            $priceFull = Arr::get((array) $item, 'price_full');
            if ($priceFull !== null && $priceFull !== '') {
                return (float) $priceFull;
            }

            return $qty * (float) Arr::get((array) $item, 'price', 0);
        });

        $order->forceFill([
            'shipping_total' => $shipping,
            'shipping_price' => $shipping,
            'sale_sum' => $discount,
            'sale_prc' => 0,
            'synced_at' => now(),
        ])->saveQuietly();

        $discountAdj = $order->adjustments()->firstOrNew([
            'type' => 'import_discount',
            'shop_order_item_id' => null,
        ]);

        if ($discount > 0) {
            $discountAdj->fill([
                'label' => 'Импорт скидки (' . $source->name . ')',
                'amount' => -$discount,
                'meta' => [
                    'source' => $source->slug,
                    'source_discount' => $discount,
                ],
            ]);
            $discountAdj->save();
        } elseif ($discountAdj->exists) {
            $discountAdj->delete();
        }

        $order->adjustments()
            ->where('type', 'import_total_correction')
            ->whereNull('shop_order_item_id')
            ->delete();

        $order->unsetRelation('items');
        $order->load('items.modifiers');

        app(OrderPricing::class)->recalc($order);

        if ($expectedTotal > 0) {
            $order->refresh();
            $currentGrand = (float) $order->grand_total;
            $delta = round($expectedTotal - $currentGrand, 2);

            $localSubtotal = (float) $order->subtotal;
            $canApplyTotalCorrection = $payloadItems !== []
                && abs(round($localSubtotal - $payloadItemsSubtotal, 2)) < 0.01;

            if (! $canApplyTotalCorrection) {
                $order->adjustments()
                    ->where('type', 'import_total_correction')
                    ->whereNull('shop_order_item_id')
                    ->delete();
            }

            if ($canApplyTotalCorrection && abs($delta) >= 0.01) {
                $order->adjustments()->updateOrCreate(
                    [
                        'type' => 'import_total_correction',
                        'shop_order_item_id' => null,
                    ],
                    [
                        'label' => 'Коррекция итога импорта (' . $source->name . ')',
                        'amount' => $delta,
                        'meta' => [
                                'source' => $source->slug,
                                'expected_total' => $expectedTotal,
                                'was_grand_total' => $currentGrand,
                                'payload_items_subtotal' => $payloadItemsSubtotal,
                                'local_subtotal' => $localSubtotal,
                            ],
                        ]
                    );

                    $order->unsetRelation('items');
                $order->load('items.modifiers');
                app(OrderPricing::class)->recalc($order);
            }
        }

        $order->refresh();
        $order->recalculateTotalPrice();

        $order->forceFill([
            'sale_sum' => $discount,
            'sale_prc' => 0,
            'total_price_sale' => max(0, round((float) $order->total_price - $discount, 2)),
        ])->saveQuietly();

        $hasUnmatched = $hasUnmatchedOverride ?? $order->items()->whereNull('product_id')->exists();
        $order->forceFill(['has_unmatched_items' => $hasUnmatched])->saveQuietly();
    }

    protected function ensureLocalClient(Source $source, array $orderPayload): Client
    {
        $externalId = Arr::get($orderPayload, 'user_id');
        $phone = $this->normalizePhone((string) Arr::get($orderPayload, 'phone', ''));
        $name = trim((string) Arr::get($orderPayload, 'name', ''));
        $email = trim((string) Arr::get($orderPayload, 'email', ''));

        $sourceClient = SourceClient::query()
            ->where('source_id', $source->id)
            ->when($phone !== '', fn ($q) => $q->where('external_phone', $phone))
            ->first();

        if ($sourceClient?->local_client_id) {
            $local = Client::find($sourceClient->local_client_id);
            if ($local) {
                return $local;
            }
        }

        $localClient = null;
        if ($phone !== '') {
            $localClient = Client::query()->where('phone', $phone)->first();
        }

        if (! $localClient) {
            $localClient = Client::create([
                'name' => $name !== '' ? $name : ('Клиент ' . $source->name),
                'phone' => $phone !== '' ? $phone : ('380000' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT)),
                'email' => $email !== '' ? $email : null,
                'is_active' => true,
            ]);
        } else {
            $localClient->fill([
                'name' => $localClient->name ?: ($name !== '' ? $name : $localClient->name),
                'email' => $localClient->email ?: ($email !== '' ? $email : $localClient->email),
            ])->saveQuietly();
        }

        SourceClient::updateOrCreate(
            [
                'source_id' => $source->id,
                'external_phone' => $phone !== '' ? $phone : null,
            ],
            [
                'external_id' => $externalId ? (string) $externalId : null,
                'name' => $name !== '' ? $name : null,
                'email' => $email !== '' ? $email : null,
                'local_client_id' => $localClient->id,
                'payload' => [
                    'name' => Arr::get($orderPayload, 'name'),
                    'email' => Arr::get($orderPayload, 'email'),
                    'phone' => Arr::get($orderPayload, 'phone'),
                ],
                'synced_at' => now(),
            ]
        );

        return $localClient;
    }

    protected function ensureClientAddress(Client $client, array $orderPayload): ?ClientAddress
    {
        $street = trim((string) Arr::get($orderPayload, 'address', ''));
        $house = trim((string) Arr::get($orderPayload, 'build', ''));
        $apartment = trim((string) Arr::get($orderPayload, 'kvartira', ''));

        if ($street === '' && $house === '') {
            return null;
        }

        return ClientAddress::firstOrCreate(
            [
                'client_id' => $client->id,
                'street' => $street !== '' ? $street : 'Не вказано',
                'house' => $house !== '' ? $house : '—',
                'apartment' => $apartment !== '' ? $apartment : null,
            ],
            [
                'entrance' => trim((string) Arr::get($orderPayload, 'parad', '')) ?: null,
                'floor' => trim((string) Arr::get($orderPayload, 'floar', '')) ?: null,
            ]
        );
    }

    protected function ensureLocalProductByExternalId(Source $source, string $externalProductId, array $itemPayload = []): ?Product
    {
        if ($externalProductId === '') {
            return null;
        }

        $sourceProduct = SourceProduct::query()
            ->where('source_id', $source->id)
            ->where('external_id', $externalProductId)
            ->first();

        if ($sourceProduct && $sourceProduct->local_product_id) {
            $local = Product::find($sourceProduct->local_product_id);
            if ($local) {
                return $local;
            }
        }

        if (! ($this->catalogRefreshState[$source->slug] ?? false)) {
            $this->syncCatalogForSource($source);
            $this->catalogRefreshState[$source->slug] = true;
            $sourceProduct = SourceProduct::query()
                ->where('source_id', $source->id)
                ->where('external_id', $externalProductId)
                ->first();
        }

        if ($sourceProduct) {
            return $this->ensureLocalProductFromSourceProduct($source, $sourceProduct, $sourceProduct->payload ?? []);
        }

        $fallbackCategory = $this->ensureFallbackCategory($source);
        $titleBase = trim((string) Arr::get($itemPayload, 'name', ''));
        $titleBase = $titleBase !== '' ? $titleBase : ('Товар #' . $externalProductId);

        $product = Product::query()->where('code2', $externalProductId)->first();
        if (! $product) {
            $slug = $this->uniqueSlug('src-' . $source->slug . '-p-' . $externalProductId, Product::class);
            $product = Product::create([
                'title' => $this->singleTitle($titleBase),
                'description' => $this->singleTitle('Импортировано из ' . $source->name),
                'slug' => $slug,
                'price' => (float) Arr::get($itemPayload, 'price', Arr::get($itemPayload, 'price_full', 0)),
                'in_stock' => true,
                'quantity' => 1000,
                'category_id' => $fallbackCategory?->id,
                'short_name' => Str::limit($titleBase, 250, ''),
                'code2' => $externalProductId,
                'sku' => $this->resolveSafeSku(trim((string) Arr::get($itemPayload, 'weight', ''))),
            ]);
        }

        SourceProduct::updateOrCreate(
            [
                'source_id' => $source->id,
                'external_id' => $externalProductId,
            ],
            [
                'title' => $titleBase,
                'size_label' => trim((string) Arr::get($itemPayload, 'weight', '')) ?: null,
                'price' => (float) Arr::get($itemPayload, 'price', Arr::get($itemPayload, 'price_full', 0)),
                'local_product_id' => $product->id,
                'payload' => $itemPayload,
                'synced_at' => now(),
            ]
        );

        return $product;
    }

    protected function ensureLocalProductFromSourceProduct(Source $source, SourceProduct $sourceProduct, array $variantPayload): Product
    {
        $categoryId = $this->resolveLocalCategoryId($source, $sourceProduct->external_category_id, $variantPayload);
        $baseTitle = trim((string) ($sourceProduct->title ?: Arr::get($variantPayload, 'title.uk') ?: Arr::get($variantPayload, 'title.ru') ?: Arr::get($variantPayload, 'title.en') ?: Arr::get($variantPayload, 'title')));
        $baseTitle = $baseTitle !== '' ? $baseTitle : ('Товар #' . $sourceProduct->external_id);

        $sizeLabel = trim((string) ($sourceProduct->size_label ?: Arr::get($variantPayload, 'size_label', '')));
        $fullTitle = trim($baseTitle . ($sizeLabel !== '' ? (' ' . $sizeLabel) : ''));
        $imageUrl = $this->normalizeSourceImageUrl($source, Arr::get($variantPayload, 'img'));

        $product = null;
        if ($sourceProduct->local_product_id) {
            $product = Product::find($sourceProduct->local_product_id);
        }
        if (! $product) {
            $product = Product::query()->where('code2', (string) $sourceProduct->external_id)->first();
        }

        if (! $product) {
            $slug = $this->uniqueSlug('src-' . $source->slug . '-p-' . $sourceProduct->external_id, Product::class);
            $product = Product::create([
                'title' => $this->mergeTitles($variantPayload, $fullTitle),
                'description' => $this->mergeDescriptions($variantPayload),
                'slug' => $slug,
                'price' => (float) ($sourceProduct->price ?? Arr::get($variantPayload, 'price', 0)),
                'in_stock' => true,
                'quantity' => 1000,
                'category_id' => $categoryId,
                'short_name' => Str::limit($fullTitle, 250, ''),
                'code2' => (string) $sourceProduct->external_id,
                'sku' => $this->resolveSafeSku($sizeLabel),
                'main_image' => $imageUrl !== '' ? $imageUrl : null,
            ]);
        } else {
            $product->fill([
                'price' => (float) ($sourceProduct->price ?? Arr::get($variantPayload, 'price', $product->price)),
                'category_id' => $categoryId ?: $product->category_id,
                'in_stock' => true,
                'main_image' => $imageUrl !== '' ? $imageUrl : $product->main_image,
            ])->saveQuietly();
        }

        $sourceProduct->forceFill([
            'local_product_id' => $product->id,
            'synced_at' => now(),
        ])->saveQuietly();

        return $product;
    }

    protected function normalizeSourceImageUrl(Source $source, mixed $image): ?string
    {
        $path = trim((string) $image);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $sourceHost = parse_url((string) $source->base_url, PHP_URL_HOST);
            $imageHost = parse_url($path, PHP_URL_HOST);

            if ($sourceHost && $imageHost && strcasecmp($sourceHost, $imageHost) !== 0) {
                if (str_contains(mb_strtolower($imageHost), 'pirogovaya.online')) {
                    $pathPart = (string) parse_url($path, PHP_URL_PATH);
                    $queryPart = (string) parse_url($path, PHP_URL_QUERY);
                    $base = rtrim((string) $source->base_url, '/');

                    return $base . '/' . ltrim($pathPart, '/') . ($queryPart !== '' ? ('?' . $queryPart) : '');
                }
            }

            return $path;
        }

        if (Str::startsWith($path, '//')) {
            return 'https:' . $path;
        }

        $base = rtrim((string) $source->base_url, '/');
        if ($base === '') {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }

    protected function resolveLocalCategoryId(Source $source, ?string $externalCategoryId, array $variantPayload): ?int
    {
        if ($externalCategoryId) {
            $sourceCategory = SourceCategory::query()
                ->where('source_id', $source->id)
                ->where('external_id', (string) $externalCategoryId)
                ->first();
            if ($sourceCategory?->local_category_id) {
                return $sourceCategory->local_category_id;
            }
        }

        $categoryPayload = Arr::first((array) Arr::get($variantPayload, 'categories', []));
        if (is_array($categoryPayload) && isset($categoryPayload['id'])) {
            [$sourceCategory] = $this->upsertSourceCategory($source, [
                'id' => $categoryPayload['id'],
                'alias' => Arr::get($categoryPayload, 'alias'),
                'category_id' => Arr::get($categoryPayload, 'category_id', -1),
                'title' => Arr::get($categoryPayload, 'title'),
                'text' => Arr::get($categoryPayload, 'text'),
            ]);
            return $sourceCategory->local_category_id ?: null;
        }

        return $this->ensureFallbackCategory($source)?->id;
    }

    protected function ensureFallbackCategory(Source $source): ?ProductCategory
    {
        $externalId = '__fallback__';
        $row = SourceCategory::query()
            ->where('source_id', $source->id)
            ->where('external_id', $externalId)
            ->first();

        if ($row?->local_category_id) {
            return ProductCategory::find($row->local_category_id);
        }

        $title = [
            'uk' => 'Імпорт: ' . $source->name,
            'ru' => 'Импорт: ' . $source->name,
            'en' => 'Import: ' . $source->name,
        ];

        $category = ProductCategory::create([
            'title' => $title,
            'slug' => $this->uniqueSlug('src-' . $source->slug . '-import', ProductCategory::class),
            'parent_id' => null,
            'is_visible' => true,
            'description' => [
                'uk' => 'Категорія для автоматично імпортованих товарів',
                'ru' => 'Категория для автоматически импортированных товаров',
                'en' => 'Category for auto imported products',
            ],
        ]);

        SourceCategory::updateOrCreate(
            [
                'source_id' => $source->id,
                'external_id' => $externalId,
            ],
            [
                'title' => $title,
                'local_category_id' => $category->id,
                'payload' => ['type' => 'fallback'],
                'synced_at' => now(),
            ]
        );

        return $category;
    }

    protected function upsertSourceCategory(Source $source, array $categoryPayload): array
    {
        $externalId = (string) Arr::get($categoryPayload, 'id', '');
        if ($externalId === '') {
            throw new \RuntimeException('Category external id is empty');
        }

        $translations = $this->extractCategoryTitleTranslations($categoryPayload);
        $description = $this->extractCategoryDescriptionTranslations($categoryPayload);
        $alias = Arr::get($categoryPayload, 'alias');

        $model = SourceCategory::query()
            ->where('source_id', $source->id)
            ->where('external_id', $externalId)
            ->first();

        $isNew = ! $model;
        if (! $model) {
            $model = new SourceCategory();
            $model->source_id = $source->id;
            $model->external_id = $externalId;
        }

        $model->external_parent_id = $this->normalizeParentExternalId(Arr::get($categoryPayload, 'category_id'));
        $model->alias = is_string($alias) ? $alias : null;
        $model->title = $translations;
        $model->payload = $categoryPayload;
        $model->synced_at = now();
        $model->save();

        $localCategory = $model->local_category_id ? ProductCategory::find($model->local_category_id) : null;
        if (! $localCategory) {
            $localCategory = ProductCategory::create([
                'title' => $translations,
                'slug' => $this->uniqueSlug('src-' . $source->slug . '-cat-' . $externalId, ProductCategory::class),
                'parent_id' => null,
                'is_visible' => true,
                'description' => $description,
            ]);

            $model->forceFill(['local_category_id' => $localCategory->id])->saveQuietly();
        }

        return [$model, $isNew];
    }

    protected function upsertSourceProduct(Source $source, array $variantPayload): array
    {
        $externalId = (string) Arr::get($variantPayload, 'external_id', '');
        if ($externalId === '') {
            throw new \RuntimeException('Product external id is empty');
        }

        $row = SourceProduct::query()
            ->where('source_id', $source->id)
            ->where('external_id', $externalId)
            ->first();

        $isNew = ! $row;
        if (! $row) {
            $row = new SourceProduct();
            $row->source_id = $source->id;
            $row->external_id = $externalId;
        }

        $row->external_parent_id = (string) Arr::get($variantPayload, 'external_parent_id', '');
        $row->external_category_id = Arr::get($variantPayload, 'external_category_id') ? (string) Arr::get($variantPayload, 'external_category_id') : null;
        $row->alias = Arr::get($variantPayload, 'alias');
        $row->title = Str::limit((string) Arr::get($variantPayload, 'plain_title', ''), 255, '');
        $row->size_label = Str::limit((string) Arr::get($variantPayload, 'size_label', ''), 255, '');
        $row->price = (float) Arr::get($variantPayload, 'price', 0);
        $row->payload = $variantPayload;
        $row->synced_at = now();
        $row->save();

        return [$row, $isNew];
    }

    protected function syncCategoryParents(Source $source): void
    {
        $categories = SourceCategory::query()->where('source_id', $source->id)->get()->keyBy('external_id');

        foreach ($categories as $category) {
            if (! $category->local_category_id) {
                continue;
            }

            $localCategory = ProductCategory::find($category->local_category_id);
            if (! $localCategory) {
                continue;
            }

            $parentExternalId = $category->external_parent_id;
            $parentLocalId = null;

            if ($parentExternalId && isset($categories[$parentExternalId])) {
                $parentLocalId = $categories[$parentExternalId]->local_category_id;
            }

            if ($localCategory->parent_id !== $parentLocalId) {
                $localCategory->forceFill(['parent_id' => $parentLocalId])->saveQuietly();
            }
        }
    }

    protected function activeSources()
    {
        $this->bootstrapConfiguredSources();

        return Source::query()
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->orderBy('id')
            ->get();
    }

    public function ensureConfiguredSources(): void
    {
        $this->bootstrapConfiguredSources();
    }

    protected function bootstrapConfiguredSources(): void
    {
        $this->bootstrapSourceFromConfig('services.pirogovaya_api', 'Pirogovaya', 'pirogovaya');
        $this->bootstrapSourceFromConfig('services.pie_api', 'Pie', 'pie');
    }

    protected function bootstrapSourceFromConfig(string $configPath, string $defaultName, string $defaultSlug): void
    {
        $enabled = (bool) config("{$configPath}.enabled", false);
        $name = (string) config("{$configPath}.name", $defaultName);
        $slug = (string) config("{$configPath}.slug", $defaultSlug);
        $baseUrl = trim((string) config("{$configPath}.base_url", ''));
        $apiKey = trim((string) config("{$configPath}.api_key", ''));

        if (! $enabled || $baseUrl === '' || $apiKey === '' || $slug === '') {
            return;
        }

        Source::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'base_url' => rtrim($baseUrl, '/'),
                'api_key' => $apiKey,
                'is_active' => true,
                'sync_enabled' => true,
            ]
        );
    }

    protected function fetchSourceOrders(Source $source, int $limit): array
    {
        $configuredEndpoint = null;
        if ($source->slug === (string) config('services.pirogovaya_api.slug', 'pirogovaya')) {
            $configuredEndpoint = (string) config('services.pirogovaya_api.orders_endpoint', '/api/get-last-orders');
        } elseif ($source->slug === (string) config('services.pie_api.slug', 'pie')) {
            $configuredEndpoint = (string) config('services.pie_api.orders_endpoint', '/api/get-last-orders');
        }

        $endpoints = collect([
            $configuredEndpoint,
            '/api/get-last-orders',
            '/api/getLastOrders',
        ])
            ->filter(fn ($endpoint) => is_string($endpoint) && trim($endpoint) !== '')
            ->map(fn (string $endpoint) => '/' . ltrim(trim($endpoint), '/'))
            ->unique()
            ->values();

        $lastError = null;
        $response = null;

        foreach ($endpoints as $endpoint) {
            try {
                $response = $this->apiGet($source, $endpoint, [
                    'apikey' => $source->api_key,
                    'limit' => min(max($limit, 1), 200),
                ]);
                break;
            } catch (\Throwable $e) {
                $lastError = $e;
                if (! str_contains($e->getMessage(), ' 404 ')) {
                    throw $e;
                }
            }
        }

        if (! is_array($response)) {
            $hint = "Endpoint orders not found on source API. Check source code has actionGetLastOrders and route /api/get-last-orders, or set CALLCENTER_PIROGOVAYA_ORDERS_ENDPOINT.";
            $msg = $lastError ? ($lastError->getMessage() . ' | ' . $hint) : $hint;
            throw new \RuntimeException($msg);
        }

        if (isset($response['orders']) && is_array($response['orders'])) {
            return $response['orders'];
        }

        return is_array($response) && array_is_list($response) ? $response : [];
    }

    protected function fetchSourceCategories(Source $source): array
    {
        $response = $this->apiGet($source, '/api/get-category', []);

        return is_array($response) ? $response : [];
    }

    protected function fetchSourceProducts(Source $source): array
    {
        $response = $this->apiGet($source, '/api/get-product', [
            'limit' => 1000,
            'offset' => 0,
        ]);

        if (is_array($response) && array_is_list($response)) {
            return $response;
        }

        return [];
    }

    protected function apiGet(Source $source, string $endpoint, array $query): array
    {
        $url = rtrim($source->base_url, '/') . '/' . ltrim($endpoint, '/');
        $response = Http::timeout(30)->acceptJson()->get($url, $query);

        if (! $response->ok()) {
            throw new \RuntimeException("API request failed {$response->status()} for {$source->name}: {$endpoint}");
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \RuntimeException("Invalid API response for {$source->name}: {$endpoint}");
        }

        if (isset($json['error'])) {
            throw new \RuntimeException((string) $json['error']);
        }

        return $json;
    }

    protected function extractProductVariants(array $productPayload): array
    {
        $variants = [];
        $params = (array) Arr::get($productPayload, 'params', []);
        $diameters = Arr::get($params, 'diametrs', []);
        $volumes = Arr::get($params, 'volume', []);
        $allParams = array_merge(is_array($diameters) ? $diameters : [], is_array($volumes) ? $volumes : []);

        $categoryExternalId = null;
        $categories = Arr::get($productPayload, 'category', []);
        if (is_array($categories) && isset($categories[0]['id'])) {
            $categoryExternalId = (string) $categories[0]['id'];
        }

        if ($allParams === []) {
            $variants[] = [
                'external_id' => (string) Arr::get($productPayload, 'id'),
                'external_parent_id' => (string) Arr::get($productPayload, 'id'),
                'external_category_id' => $categoryExternalId,
                'alias' => Arr::get($productPayload, 'alias'),
                'img' => Arr::get($productPayload, 'img'),
                'title' => Arr::get($productPayload, 'title', []),
                'plain_title' => Arr::get($productPayload, 'title.uk') ?: Arr::get($productPayload, 'title.ru') ?: Arr::get($productPayload, 'title.en') ?: 'Imported product',
                'size_label' => null,
                'price' => (float) Arr::get($productPayload, 'price', 0),
                'categories' => $categories,
                'description' => Arr::get($productPayload, 'text', []),
            ];

            return $variants;
        }

        foreach ($allParams as $param) {
            $value = trim((string) Arr::get($param, 'value', ''));
            $weight = trim((string) Arr::get($param, 'weight', ''));
            $unit = trim((string) Arr::get($param, 'mera', ''));

            $sizeLabel = trim(implode(' ', array_filter([
                $value !== '' ? $value : null,
                $unit !== '' ? $unit : null,
                $weight !== '' ? "({$weight} г)" : null,
            ])));

            $variants[] = [
                'external_id' => (string) Arr::get($param, 'id'),
                'external_parent_id' => (string) Arr::get($productPayload, 'id'),
                'external_category_id' => $categoryExternalId,
                'alias' => Arr::get($productPayload, 'alias'),
                'img' => Arr::get($productPayload, 'img'),
                'title' => Arr::get($productPayload, 'title', []),
                'plain_title' => Arr::get($productPayload, 'title.uk') ?: Arr::get($productPayload, 'title.ru') ?: Arr::get($productPayload, 'title.en') ?: 'Imported product',
                'size_label' => $sizeLabel !== '' ? $sizeLabel : null,
                'price' => (float) Arr::get($param, 'price', 0),
                'categories' => $categories,
                'description' => Arr::get($productPayload, 'text', []),
            ];
        }

        return $variants;
    }

    protected function mapStatus(int $sourceStatus): string
    {
        return match ($sourceStatus) {
            6 => OrderStatus::Delivered->value,
            7, 8 => OrderStatus::Cancelled->value,
            default => OrderStatus::New->value,
        };
    }

    protected function mapPayment(int $sourcePayment): int
    {
        $allowed = collect(PaymentMethodEnum::cases())->map(fn (PaymentMethodEnum $case) => $case->value)->all();
        if (in_array($sourcePayment, $allowed, true)) {
            return $sourcePayment;
        }

        return PaymentMethodEnum::CARD->value;
    }

    protected function isSelfPickup(array $orderPayload): bool
    {
        $deliveryId = (int) Arr::get($orderPayload, 'delivery_id', 0);
        if ($deliveryId === 7) {
            return true;
        }

        $street = trim((string) Arr::get($orderPayload, 'address', ''));

        return $street === '';
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (Str::startsWith($digits, '0')) {
            $digits = '38' . $digits;
        }

        if (strlen($digits) === 9) {
            $digits = '380' . $digits;
        }

        return $digits;
    }

    protected function uniqueSlug(string $baseSlug, string $modelClass): string
    {
        $slug = Str::slug($baseSlug);
        $slug = $slug !== '' ? $slug : Str::random(10);

        $candidate = $slug;
        $counter = 2;

        while ($modelClass::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    protected function mergeTitles(array $variantPayload, string $fallback): array
    {
        $raw = Arr::get($variantPayload, 'title', []);
        $size = trim((string) Arr::get($variantPayload, 'size_label', ''));

        $titles = [
            'uk' => $fallback,
            'ru' => $fallback,
            'en' => $fallback,
        ];

        if (is_array($raw)) {
            foreach (['uk', 'ru', 'en'] as $locale) {
                $base = trim((string) Arr::get($raw, $locale, $titles[$locale]));
                if ($base !== '') {
                    $titles[$locale] = trim($base . ($size !== '' ? (' ' . $size) : ''));
                }
            }
        }

        return $titles;
    }

    protected function mergeDescriptions(array $variantPayload): array
    {
        $raw = Arr::get($variantPayload, 'description', []);
        if (! is_array($raw)) {
            return [
                'uk' => '',
                'ru' => '',
                'en' => '',
            ];
        }

        return [
            'uk' => (string) Arr::get($raw, 'uk', ''),
            'ru' => (string) Arr::get($raw, 'ru', ''),
            'en' => (string) Arr::get($raw, 'en', ''),
        ];
    }

    protected function singleTitle(string $title): array
    {
        return [
            'uk' => $title,
            'ru' => $title,
            'en' => $title,
        ];
    }

    protected function resolveSafeSku(?string $sku, ?int $exceptProductId = null): ?string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return null;
        }

        $exists = Product::query()
            ->where('sku', $sku)
            ->when($exceptProductId !== null, fn ($q) => $q->whereKeyNot($exceptProductId))
            ->exists();

        return $exists ? null : $sku;
    }

    protected function normalizeParentExternalId(mixed $value): ?string
    {
        $normalized = trim((string) $value);
        if ($normalized === '' || $normalized === '-1' || $normalized === '0') {
            return null;
        }

        return $normalized;
    }

    protected function extractCategoryTitleTranslations(array $categoryPayload): array
    {
        $info = Arr::get($categoryPayload, 'info');

        if (is_array($info) && array_is_list($info)) {
            return $this->mapInfoRowsByLang($info, 'title');
        }

        $titles = Arr::get($categoryPayload, 'title');
        if (is_array($titles)) {
            return [
                'uk' => (string) Arr::get($titles, 'uk', Arr::get($titles, 'ua', '')),
                'ru' => (string) Arr::get($titles, 'ru', ''),
                'en' => (string) Arr::get($titles, 'en', ''),
            ];
        }

        $fallback = trim((string) Arr::get($categoryPayload, 'alias', 'Категория'));

        return [
            'uk' => $fallback,
            'ru' => $fallback,
            'en' => $fallback,
        ];
    }

    protected function extractCategoryDescriptionTranslations(array $categoryPayload): array
    {
        $info = Arr::get($categoryPayload, 'info');
        if (is_array($info) && array_is_list($info)) {
            return $this->mapInfoRowsByLang($info, 'text');
        }

        $text = Arr::get($categoryPayload, 'text');
        if (is_array($text)) {
            return [
                'uk' => (string) Arr::get($text, 'uk', Arr::get($text, 'ua', '')),
                'ru' => (string) Arr::get($text, 'ru', ''),
                'en' => (string) Arr::get($text, 'en', ''),
            ];
        }

        return [
            'uk' => '',
            'ru' => '',
            'en' => '',
        ];
    }

    protected function mapInfoRowsByLang(array $rows, string $key): array
    {
        $mapped = [
            'uk' => '',
            'ru' => '',
            'en' => '',
        ];

        foreach ($rows as $row) {
            $langId = (int) Arr::get($row, 'lang', 0);
            $value = (string) Arr::get($row, $key, '');

            if ($langId === 1) {
                $mapped['ru'] = $value;
            } elseif ($langId === 2) {
                $mapped['uk'] = $value;
            } elseif ($langId === 3) {
                $mapped['en'] = $value;
            }
        }

        $fallback = $mapped['uk'] ?: ($mapped['ru'] ?: $mapped['en']);
        if ($mapped['uk'] === '') {
            $mapped['uk'] = $fallback;
        }
        if ($mapped['ru'] === '') {
            $mapped['ru'] = $fallback;
        }
        if ($mapped['en'] === '') {
            $mapped['en'] = $fallback;
        }

        return $mapped;
    }
}
