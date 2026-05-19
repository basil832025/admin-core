<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Enums\OrderStatus;
use App\Models\Shop\OrderItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CartService
{
    const SESSION_KEY = 'cart.items'; // [ [product_id, qty, price, meta], ... ]
    private const COOKIE_KEY = 'guest_cart';
    private const COOKIE_MINUTES = 60 * 24 * 30;

    private ?Authenticatable $cachedUser = null;
    private bool $cachedUserResolved = false;
    private array $draftOrderCache = [];
    private array $cartItemsCache = [];
    private array $cartItemsByProductIdCache = [];
    private array $variantAttributesCache = [];
    private bool $guestCartLoaded = false;
    private array $guestCartCache = [];

    /** Добавить товар */
    public function add(int $productId, int $qty = 1, ?float $price = null, array $meta = []): array
    {
        $user = $this->authUser();

        if ($user) {
            $this->addForUser($user, $productId, $qty, $price, $meta);
        } else {
            $this->addToSession($productId, $qty, $price, $meta);
        }

        $this->resetCheckoutState();

        [$cartTotalQty, $cartTotalSum] = $this->computeTotals();
        $lineArray = $this->getLineArrayByProductId($productId);

        return array_merge(
            $this->buildSummaryPayload($cartTotalQty, $cartTotalSum),
            $this->buildItemPayload($lineArray)
        );
    }

    /** Удалить товар */
    public function remove(int $productId): array
    {
        $user = $this->authUser();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            $order->items()->where('product_id', $productId)->delete();
            $this->recalc($order);
        } else {
            $items = collect($this->guestCartItems())
                ->reject(fn($i) => (int)$i['product_id'] === $productId)
                ->values()
                ->all();
            $this->storeGuestCartItems($items);
        }

        $this->resetCheckoutState();

        [$cartTotalQty, $cartTotalSum] = $this->computeTotals();
        $lineArray = $this->getLineArrayByProductId($productId);

        return array_merge(
            $this->buildSummaryPayload($cartTotalQty, $cartTotalSum),
            $this->buildItemPayload($lineArray)
        );
    }

    /** Очистить корзину */
    public function clear(): array
    {
        $user = $this->authUser();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            $order->items()->delete();
            $this->recalc($order);
        } else {
            $this->forgetGuestCartItems();
        }

        $this->resetCheckoutState();

        return $this->buildSummaryPayload(0, 0);
    }

    /** Информация по корзине (для бейджа, мини-корзины и т.п.) */
    public function info(): array
    {
        $user = $this->authUser();

        if ($user) {
            // только читаем, не создаём
            $order = $this->getOrCreateDraftOrder($user, false);

            if (! $order) {
                // у пользователя ещё нет корзины – считаем, что она пустая
                return [
                    'ok'          => true,
                    'qty'         => 0,
                    'total_price' => 0.0,
                    'items'       => [],
                ];
            }

                return [
                    'ok'          => true,
                    'qty'         => (int) $this->cartOrderItems($order)->sum('qty'),
                    'total_price' => (float) $order->total_price,
                    'items'       => $this->items(),
                ];
        }

        // гость
        return [
            'ok'          => true,
            'qty'         => $this->sessionQty(),
            'total_price' => $this->sessionTotal(),
            'items'       => $this->items(),
        ];
    }


    // --------------------------------------------------------------------
    //                          PRIVATE HELPERS
    // --------------------------------------------------------------------

    private function buildSummaryPayload(int $totalQty, float $totalSum): array
    {
        return [
            'ok'          => true,
            'qty'         => $totalQty,
            'total'       => $totalSum,
            'total_price' => $totalSum,
        ];
    }

    private function normalizeUnitPrice(array $row): float
    {
        return (float)($row['price'] ?? $row['unit_price'] ?? 0);
    }

    private function buildItemPayload(?array $row): array
    {
        if (!$row) {
            return ['item' => null, 'removed' => true];
        }

        $pid  = (int)($row['product_id'] ?? $row['id'] ?? 0);
        $qty  = (int)($row['qty'] ?? 0);
        $unit = $this->normalizeUnitPrice($row);
        $oldUnit = isset($row['old_price'])
            ? (float) $row['old_price']
            : $this->resolveOldUnitPrice($pid, $unit);

        if ($oldUnit <= $unit) {
            $oldUnit = null;
        }

        return [
            'item' => [
                'product_id' => $pid,
                'qty'        => $qty,
                'unit_price' => $unit,
                'line_total' => $unit * $qty,
                'old_unit_price' => $oldUnit,
                'old_line_total' => $oldUnit ? $oldUnit * $qty : null,
            ],
            'removed' => $qty <= 0,
        ];
    }

    private function resolveOldUnitPrice(int $productId, float $currentUnitPrice): ?float
    {
        if ($productId <= 0 || $currentUnitPrice <= 0) {
            return null;
        }

        $product = app(\App\Models\Shop\Product::class)
            ->newQuery()
            ->with('parent:id,old_price')
            ->select(['id', 'parent_id', 'old_price'])
            ->find($productId);

        if (!$product) {
            return null;
        }

        return $this->resolveDisplayedOldPrice($product, $currentUnitPrice);
    }

    private function resolveDisplayedOldPrice(?\App\Models\Shop\Product $product, float $currentUnitPrice): ?float
    {
        if (! $product || $currentUnitPrice <= 0) {
            return null;
        }

        $productOldPrice = (float) ($product->old_price ?? 0);
        if ($productOldPrice > $currentUnitPrice) {
            return $productOldPrice;
        }

        $parentOldPrice = (float) ($product->parent?->old_price ?? 0);
        if ($product->parent_id && $parentOldPrice > $currentUnitPrice) {
            return $parentOldPrice;
        }

        return null;
    }

    private function getLineArrayByProductId(int $productId): ?array
    {
        $items = $this->normalizeSessionCart();

        if (is_array($items) && $items) {
            // 1) Ассоциативный вариант: ['123' => ['qty'=>..,'price'=>..], ...]
            if (isset($items[$productId]) && is_array($items[$productId])) {
                $r = $items[$productId];
                return [
                    'product_id' => $productId,
                    'qty'        => (int)($r['qty'] ?? 0),
                    'price'      => (float)($r['price'] ?? $r['unit_price'] ?? 0),
                ];
            }

            // 2) Список: [ ['product_id'=>123,'qty'=>..,'price'=>..], ...]
            foreach ($items as $k => $r) {
                // pid может быть внутри элемента, либо это ключ массива
                $pid = isset($r['product_id'])
                    ? (int)$r['product_id']
                    : (is_numeric($k) ? null : (int)$k);

                if ($pid === $productId) {
                    return [
                        'product_id' => $productId,
                        'qty'        => (int)($r['qty'] ?? 0),
                        'price'      => (float)($r['price'] ?? $r['unit_price'] ?? 0),
                    ];
                }
            }
        }

        // Авторизованный — читаем из OrderItem
        if ($user = $this->authUser()) {
            $order = $this->getOrCreateDraftOrder($user, false);
            if ($oi = $this->cartOrderItemsByProductId($order)->get($productId)) {
                return [
                    'product_id' => (int)$oi->product_id,
                    'qty'        => (int)$oi->qty,
                    'price'      => (float)($oi->unit_price ?? $oi->price ?? 0),
                ];
            }
        }

        return null;
    }

    /** Приводим сессию к единому формату: список уникальных product_id */
    private function normalizeSessionCart(): array
    {
        $raw = $this->guestCartItems();
        $acc = [];

        if (!is_array($raw)) {
            $this->storeGuestCartItems([]);
            return [];
        }

        foreach ($raw as $k => $v) {
            if (!is_array($v)) continue;

            // вариант А: список элементов
            if (array_key_exists('product_id', $v)) {
                $pid   = (int) $v['product_id'];
                $qty   = (int) ($v['qty'] ?? 0);
                $price = (float) ($v['price'] ?? $v['unit_price'] ?? 0);
                $meta  = $v['meta'] ?? [];
            }
            // вариант Б: ассоциативная карта [pid => {qty, price}]
            else {
                if (!is_numeric($k)) {
                    $pid   = (int) $k;
                    $qty   = (int) ($v['qty'] ?? 0);
                    $price = (float) ($v['price'] ?? $v['unit_price'] ?? 0);
                    $meta  = $v['meta'] ?? [];
                } else {
                    continue;
                }
            }

            if ($pid <= 0 || $qty <= 0) continue;

            if (!isset($acc[$pid])) {
                $acc[$pid] = [
                    'product_id' => $pid,
                    'qty'        => 0,
                    'price'      => $price,
                    'meta'       => $meta,
                ];
            }
            $acc[$pid]['qty'] += $qty;
            // если пришла явная цена — считаем её актуальной
            if ($price > 0) $acc[$pid]['price'] = $price;
        }

        $items = array_values($acc);
        $this->storeGuestCartItems($items);
        return $items;
    }

    private function computeTotals(): array
    {
        $qty = 0;
        $sum = 0.0;

        $user = $this->authUser();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            foreach ($this->cartOrderItems($order) as $oi) {
                $qty += (int)$oi->qty;
                $sum += (float)$oi->qty * (float)$oi->unit_price;
            }
        } else {
            $items = $this->normalizeSessionCart();
            foreach ($items as $r) {
                $q = (int)($r['qty'] ?? 0);
                $p = (float)($r['price'] ?? 0);
                $qty += $q;
                $sum += $q * $p;
            }
        }

        return [$qty, (float)$sum];
    }
    /**
     * Переносит позиции из guest storage в черновой заказ пользователя.
     * Вызывается после логина/регистрации (слушатель MergeCartOnLogin).
     */
    public function mergeSessionIntoUser(Authenticatable $user): void
    {
        // возьмём всё из guest storage в едином формате (product_id, qty, price, meta)
        $items = $this->normalizeSessionCart();
        if (empty($items)) {
            return;
        }

        DB::transaction(function () use ($user, $items) {
            $order = $this->getOrCreateDraftOrder($user);

            foreach ($items as $i) {
                $pid   = (int)($i['product_id'] ?? 0);
                $qty   = max(1, (int)($i['qty'] ?? 0));
                $price = array_key_exists('price', $i) ? (float)$i['price'] : null;
                $meta  = $i['meta'] ?? [];

                if ($pid <= 0 || $qty <= 0) {
                    continue;
                }

                /** @var \App\Models\Shop\OrderItem $row */
                $row = $order->items()->firstOrNew(['product_id' => $pid]);
                $row->qty = ($row->exists ? (int)$row->qty : 0) + $qty;

                // если цена пришла — используем её, иначе оставляем существующую
                if ($price !== null && $price > 0) {
                    $row->unit_price = $price;
                }

                if (!empty($meta)) {
                    $row->meta = $meta;
                }

                $row->save();
            }

            $this->recalc($order);
        });

        // очистим гостевую корзину, чтобы не слить повторно
        $this->forgetGuestCartItems();
    }
    protected function sessionQty(): int
    {
        return (int) collect($this->guestCartItems())->sum('qty');
    }
// Δ-изменение количества (+1 / -1 / +N / -N)
    public function changeQty(int $productId, int $delta = 1, ?float $price = null, array $meta = []): array
    {
        $user = $this->authUser();

        if ($user) {
            // без abs()/max(1,...) — дельта может быть отрицательной
            $this->addForUser($user, $productId, $delta, $price, $meta);
        } else {
            $this->addToSession($productId, $delta, $price, $meta);
        }

        $this->resetCheckoutState();

        // общий итог + текущая строка
        [$cartTotalQty, $cartTotalSum] = $this->computeTotals();
        $lineArray = $this->getLineArrayByProductId($productId);

        return array_merge(
            $this->buildSummaryPayload($cartTotalQty, $cartTotalSum),
            $this->buildItemPayload($lineArray)
        );
    }

    /**
     * Установить абсолютное количество (ручной ввод).
     * qty < = 0 — удалить позицию.
     */
    public function setQty(int $productId, int $qty, ?float $price = null, array $meta = []): array
    {
        $qty  = max(0, (int) $qty);
        $user = $this->authUser();

        if ($user) $this->setForUser($user, $productId, $qty, $price, $meta);
        else       $this->setInSession($productId, $qty, $price, $meta);

        $this->resetCheckoutState();

        [$q,$s] = $this->computeTotals();
        $row    = $this->getLineArrayByProductId($productId);

        return array_merge($this->buildSummaryPayload($q, $s), $this->buildItemPayload($row));
    }
    protected function setInSession(int $productId, int $qty, ?float $price, array $meta): void
    {
        $items = collect($this->normalizeSessionCart());
        $idx   = $items->search(fn($i) => (int)$i['product_id'] === $productId);

        if ($qty <= 0) {
            if ($idx !== false) {
                $items->forget($idx);
                $this->storeGuestCartItems(array_values($items->all()));
            }
            return;
        }

        if ($idx === false) {
            $items->push(['product_id'=>$productId,'qty'=>$qty,'price'=>$price,'meta'=>$meta]);
        } else {
            $row = $items->get($idx);
            $row['qty'] = $qty;
            if ($price !== null) $row['price'] = $price;
            if (!empty($meta))   $row['meta']  = $meta;
            $items->put($idx, $row);
        }

        $this->storeGuestCartItems(array_values($items->all()));
    }
    protected function setForUser($user, int $productId, int $qty, ?float $price, array $meta): void
    {
        $order = $this->getOrCreateDraftOrder($user, true);
        /** @var OrderItem $item */
        $item = $order->items()->firstOrNew(['product_id' => $productId]);

        if ($qty <= 0) {
            if ($item->exists) { $item->delete(); $this->forgetOrderCaches($order); $this->recalc($order); }
            return;
        }

        $item->qty = $qty;
        if ($price !== null) $item->unit_price = $price;
        elseif (!$item->exists) $item->unit_price = $item->unit_price ?? 0;

        if (!empty($meta)) $item->meta = $meta;

        $item->save();
        $this->forgetOrderCaches($order);
        $this->recalc($order);
    }
    protected function sessionTotal(): float
    {
        return (float) collect($this->guestCartItems())
            ->sum(fn($i) => (float)($i['price'] ?? 0) * (int)$i['qty']);
    }

    // --------------------------------------------------------------------
    //                          DATABASE METHODS
    // --------------------------------------------------------------------

    // Δ-изменение для авторизованного пользователя (Order/OrderItem)
    protected function addForUser($user, int $productId, int $deltaQty, ?float $price, array $meta): void
    {
        $order = $this->getOrCreateDraftOrder($user);

        /** @var OrderItem $item */
        $item = $order->items()->firstOrNew(['product_id' => $productId]);

        // если позиции нет и дельта отрицательная — ничего не делаем
        if (!$item->exists && $deltaQty <= 0) {
            return;
        }

        $newQty = (int)($item->exists ? $item->qty : 0) + (int)$deltaQty;

        if ($newQty <= 0) {
            // qty <= 0 — удаляем строку, пересчитываем заказ
            if ($item->exists) {
                $item->delete();
                $this->forgetOrderCaches($order);
                $this->recalc($order);
            }
            return;
        }

        $item->qty = $newQty;

        // цену обновляем, только если передали явно
        if ($price !== null) {
            $item->unit_price = $price;
        } elseif (!$item->exists) {
            $item->unit_price = $item->unit_price ?? 0;
        }

        if (!empty($meta)) {
            $item->meta = $meta;
        }

        $item->save();
        $this->forgetOrderCaches($order);
        $this->recalc($order);
    }

    // Δ-изменение для гостей (сессия)
    protected function addToSession(int $productId, int $deltaQty, ?float $price, array $meta): void
    {
        $items = collect($this->normalizeSessionCart());

        $idx = $items->search(fn ($i) => (int)$i['product_id'] === $productId);

        if ($idx === false) {
            // нет строки и пришла отрицательная дельта — игнорируем
            if ($deltaQty <= 0) {
                return;
            }
            $items->push([
                'product_id' => $productId,
                'qty'        => (int)$deltaQty,  // уже > 0 по условию
                'price'      => $price,          // можно подставить дефолт из каталога, если нужно
                'meta'       => $meta,
            ]);
        } else {
            $row = $items->get($idx);
            $newQty = (int)$row['qty'] + (int)$deltaQty;

            if ($newQty <= 0) {
                // удаляем строку
                $items->forget($idx);
            } else {
                $row['qty'] = $newQty;
                if ($price !== null) $row['price'] = $price;
                if (!empty($meta))   $row['meta']  = $meta;
                $items->put($idx, $row);
            }
        }

        $this->storeGuestCartItems(array_values($items->all()));
    }

    protected function getOrCreateDraftOrder(Authenticatable $user, bool $createIfMissing = true): ?Order
    {
        $cacheKey = ($user->getAuthIdentifier() ?? 'guest').':'.($createIfMissing ? 'create' : 'readonly');

        if (array_key_exists($cacheKey, $this->draftOrderCache)) {
            return $this->draftOrderCache[$cacheKey];
        }

        $clientId = $user->getAuthIdentifier();
        
        // Проверяем, что клиент существует в базе данных
        $clientExists = \App\Models\Shop\Client::where('id', $clientId)->exists();
        
        // Если клиента нет, устанавливаем clients_id = null (для гостевых заказов)
        if (!$clientExists) {
            Log::warning('Client not found when creating draft order', [
                'client_id' => $clientId,
                'user_class' => get_class($user),
            ]);
            $clientId = null;
        }
        
        /** @var Order|null $order */
        $order = Order::query()
            ->where('clients_id', $clientId)
            ->where('status', OrderStatus::Cart)
            ->first();

        if (!$order && $createIfMissing) {
            $order = new Order();
            $order->clients_id  = $clientId; // Может быть null, если клиент не существует
            $order->status      = OrderStatus::Cart;
            $order->total_price = 0;
            $order->currency    = 'UAH'; // Значение по умолчанию для валюты
            $order->save();
        }

        // если не нашли и не создавали — вернётся null
        $order = $order?->fresh(['items']);

        return $this->draftOrderCache[$cacheKey] = $order;
    }

    private function authUser(): ?Authenticatable
    {
        if (! $this->cachedUserResolved) {
            $this->cachedUser = Auth::user();
            $this->cachedUserResolved = true;
        }

        return $this->cachedUser;
    }

    private function cartOrderItems(?Order $order): \Illuminate\Support\Collection
    {
        if (! $order) {
            return collect();
        }

        $orderId = (int) $order->id;

        if (! array_key_exists($orderId, $this->cartItemsCache)) {
            $this->cartItemsCache[$orderId] = $order->relationLoaded('items')
                ? $order->items
                : $order->items()->get();
        }

        return $this->cartItemsCache[$orderId];
    }

    private function cartOrderItemsByProductId(?Order $order): \Illuminate\Support\Collection
    {
        if (! $order) {
            return collect();
        }

        $orderId = (int) $order->id;

        if (! array_key_exists($orderId, $this->cartItemsByProductIdCache)) {
            $this->cartItemsByProductIdCache[$orderId] = $this->cartOrderItems($order)->keyBy('product_id');
        }

        return $this->cartItemsByProductIdCache[$orderId];
    }

    private function forgetOrderCaches(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $orderId = (int) $order->id;
        unset($this->cartItemsCache[$orderId], $this->cartItemsByProductIdCache[$orderId]);
    }


    protected function recalc(Order $order): void
    {
        $total = $order->items()->sum(DB::raw('qty * unit_price'));

        $order->adjustments()
            ->whereIn('type', ['fixed', 'time', 'coupon'])
            ->delete();

        $order->total_price = $total;
        $order->sale_prc = 0;
        $order->sale_sum = 0;
        $order->discount_total = 0;
        $order->subtotal = $total;
        $order->total_price_sale = $total;
        $order->grand_total = max(0, round((float) $total + (float) ($order->shipping_price ?? 0), 2));
        $order->save();
    }

    protected function resetCheckoutState(): void
    {
        if (! $this->authUser() && ! $this->hasSessionCookie()) {
            return;
        }

        $formData = Session::get('checkout.form_data', []);

        unset(
            $formData['selected_promo'],
            $formData['use_bonus'],
            $formData['bonus_amount']
        );

        Session::put('checkout.form_data', $formData);
        Session::put('checkout.selected_promo', 'none');
        Session::put('checkout.promo_discount', 0);
        Session::forget('checkout.cart_signature');
    }

    // --------------------------------------------------------------------
    //                         ITEMS (для мини-корзины)
    // --------------------------------------------------------------------

    public function items(): array
    {
        $user = $this->authUser();

        // вспомогалка: строки атрибутов из варианта (размер/вес и т.д.)
        $variantAttrs = function ($product) {
            if (! $product) {
                return '';
            }

            $productId = (int) ($product->id ?? 0);
            if ($productId <= 0) {
                return '';
            }

            if (array_key_exists($productId, $this->variantAttributesCache)) {
                return $this->variantAttributesCache[$productId];
            }

            $vals = $product->relationLoaded('productCharacteristicValues')
                ? ($product->productCharacteristicValues ?? collect())
                : collect();

            $keep  = ['rozmir-pirogiv', 'vaga']; // размер и вес
            $parts = [];

            foreach ($vals as $v) {
                $slug = (string) ($v->characteristic?->slug ?? '');
                if ($slug === '' || ! in_array($slug, $keep, true)) {
                    continue;
                }

                // приоритет: pivot.value_text → characteristicValue.value
                $text = $v->value_text ?: ($v->characteristicValue?->value ?? null);
                if ($text) {
                    $parts[] = $text;
                }
            }

            return $this->variantAttributesCache[$productId] = implode(' · ', array_filter($parts));
        };

        if ($user) {
            // Не создаём корзину, если её ещё нет – просто возвращаем пустой список.
            $order = $this->getOrCreateDraftOrder($user, false);

            if (! $order) {
                return [];
            }

            $items = $this->cartOrderItems($order);
            $productIds = $items->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->values()->all();

            $products = \App\Models\Shop\Product::query()
                ->with([
                    'parent:id,old_price,title,short_name,main_image,sku,code2',
                    'productCharacteristicValues.characteristic:id,slug',
                    'productCharacteristicValues.characteristicValue',
                ])
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            return $items->map(function (\App\Models\Shop\OrderItem $it) use ($variantAttrs, $products) {
                $p      = $products->get((int) $it->product_id); // это вариант (child) либо сам parent
                $parent = $p?->parent ?: $p;            // если есть родитель — берём его
                $oldPrice = $this->resolveDisplayedOldPrice($p, (float) $it->unit_price);

                $name  = $parent?->display_name ?? $parent?->displayName ?? $parent?->title ?? 'Товар';
                $sku   = $parent?->sku ?: null;
                $code2 = $parent?->code2 ?: null;
                $article = ($sku !== null && trim((string)$sku) !== '') ? $sku : $code2;
                $image = $parent?->main_image_url ?? ($parent?->image_url ?? null);

                return [
                    'product_id' => (int) $it->product_id,
                    'name'       => (string) $name,
                    'sku'        => $sku,
                    'image'      => $image,
                    'variant'    => $variantAttrs($p), // “33 см · 1300 г”
                    'qty'        => (int) $it->qty,
                    'price'      => (float) $it->unit_price,
                    'subtotal'   => (float) $it->qty * (float) $it->unit_price,
                    'old_price'  => $oldPrice,
                    'old_subtotal' => $oldPrice ? (float) ($it->qty * $oldPrice) : null,
                    'meta'       => $it->meta ?? [],
                    'article'    => $article,
                ];
            })->values()->all();
        }

        // Гость — из сессии + подгружаем продукты и родителей
        $rows = collect($this->guestCartItems());
        if ($rows->isEmpty()) return [];

        $ids = $rows->pluck('product_id')->all();

        /** @var \App\Models\Shop\Product $prodModel */
        $prodModel = app(\App\Models\Shop\Product::class);

        $products = $prodModel->newQuery()
            ->with([
                'parent:id,old_price,title,short_name,main_image,sku,code2',
                'productCharacteristicValues.characteristic:id,slug',
                'productCharacteristicValues.characteristicValue',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $rows->map(function ($i) use ($products, $variantAttrs) {
            $p      = $products->get((int)$i['product_id']); // child или parent
            $parent = $p?->parent ?: $p;

            $name  = $parent?->display_name ?? $parent?->displayName ?? $parent?->title ?? 'Товар';
            $sku   = $parent?->sku ?: null;
            $code2 = $parent?->code2 ?: null;
            $article = ($sku !== null && trim((string) $sku) !== '') ? $sku : $code2;
            $image = $parent?->main_image_url ?? ($parent?->image_url ?? null);

            $qty   = (int)($i['qty'] ?? 1);
            $price = (float)($i['price'] ?? 0);
            $oldPrice = $this->resolveDisplayedOldPrice($p, $price);

                return [
                    'product_id' => (int)($i['product_id'] ?? 0),
                    'name'       => (string) $name,
                    'sku'        => $sku,
                    'image'      => $image,
                    'variant'    => $variantAttrs($p),
                    'qty'        => $qty,
                    'price'      => $price,
                    'subtotal'   => $qty * $price,
                    'old_price'  => $oldPrice,
                    'old_subtotal' => $oldPrice ? $qty * $oldPrice : null,
                    'meta'       => $i['meta'] ?? [],
                    'article'    => $article,
                ];
        })->values()->all();
    }

    private function guestCartItems(): array
    {
        if ($this->guestCartLoaded) {
            return $this->guestCartCache;
        }

        $raw = request()->cookie(self::COOKIE_KEY);
        if (! is_string($raw) || $raw === '') {
            $this->guestCartLoaded = true;
            return $this->guestCartCache = [];
        }

        $decoded = json_decode($raw, true);

        $this->guestCartLoaded = true;

        return $this->guestCartCache = (is_array($decoded) ? $decoded : []);
    }

    private function storeGuestCartItems(array $items): void
    {
        $normalized = array_values(array_filter(array_map(function ($item) {
            if (! is_array($item)) {
                return null;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                return null;
            }

            return [
                'product_id' => $productId,
                'qty' => $qty,
                'price' => isset($item['price']) ? (float) $item['price'] : 0,
                'meta' => is_array($item['meta'] ?? null) ? $item['meta'] : [],
            ];
        }, $items)));

        if ($normalized === []) {
            $this->guestCartLoaded = true;
            $this->guestCartCache = [];
            $this->forgetGuestCartItems();

            return;
        }

        $this->guestCartLoaded = true;
        $this->guestCartCache = $normalized;

        Cookie::queue(cookie(self::COOKIE_KEY, json_encode($normalized), self::COOKIE_MINUTES, '/', null, null, false, false, 'lax'));
    }

    private function forgetGuestCartItems(): void
    {
        $this->guestCartLoaded = true;
        $this->guestCartCache = [];
        Cookie::queue(Cookie::forget(self::COOKIE_KEY));
    }

    private function hasSessionCookie(): bool
    {
        $sessionCookie = (string) config('session.cookie');

        return $sessionCookie !== '' && request()->cookies->has($sessionCookie);
    }

}
