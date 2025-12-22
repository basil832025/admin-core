<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Enums\OrderStatus;
use App\Models\Shop\OrderItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CartService
{
    const SESSION_KEY = 'cart.items'; // [ [product_id, qty, price, meta], ... ]

    /** Добавить товар */
    public function add(int $productId, int $qty = 1, ?float $price = null, array $meta = []): array
    {
        $user = auth()->user();

        if ($user) {
            $this->addForUser($user, $productId, $qty, $price, $meta);
        } else {
            $this->addToSession($productId, $qty, $price, $meta);
        }

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
        $user = auth()->user();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            $order->items()->where('product_id', $productId)->delete();
            $this->recalc($order);
        } else {
            $items = collect(Session::get(self::SESSION_KEY, []))
                ->reject(fn($i) => (int)$i['product_id'] === $productId)
                ->values()
                ->all();
            Session::put(self::SESSION_KEY, $items);
        }

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
        $user = auth()->user();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            $order->items()->delete();
            $this->recalc($order);
        } else {
            Session::forget(self::SESSION_KEY);
        }

        return $this->buildSummaryPayload(0, 0);
    }

    /** Информация по корзине (для бейджа, мини-корзины и т.п.) */
    public function info(): array
    {
        $user = auth()->user();

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
                'qty'         => (int) $order->items()->sum('qty'),
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

        return [
            'item' => [
                'product_id' => $pid,
                'qty'        => $qty,
                'unit_price' => $unit,
                'line_total' => $unit * $qty,
            ],
            'removed' => $qty <= 0,
        ];
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
        if ($user = auth()->user()) {
            $order = $this->getOrCreateDraftOrder($user, false);
            if ($oi = $order->items()->where('product_id', $productId)->first()) {
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
        $raw = Session::get(self::SESSION_KEY, []);
        $acc = [];

        if (!is_array($raw)) {
            Session::put(self::SESSION_KEY, []);
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
        Session::put(self::SESSION_KEY, $items);
        return $items;
    }

    private function computeTotals(): array
    {
        $qty = 0;
        $sum = 0.0;

        $user = auth()->user();

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            foreach ($order->items as $oi) {
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
     * Переносит позиции из сессии в черновой заказ пользователя.
     * Вызывается после логина/регистрации (слушатель MergeCartOnLogin).
     */
    public function mergeSessionIntoUser(Authenticatable $user): void
    {
        // возьмём всё из сессии в едином формате (product_id, qty, price, meta)
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

        // очистим корзину гость-сессии, чтобы не слить повторно
        Session::forget(self::SESSION_KEY);
    }
    protected function sessionQty(): int
    {
        return (int)collect(Session::get(self::SESSION_KEY, []))->sum('qty');
    }
// Δ-изменение количества (+1 / -1 / +N / -N)
    public function changeQty(int $productId, int $delta = 1, ?float $price = null, array $meta = []): array
    {
        $user = auth()->user();

        if ($user) {
            // без abs()/max(1,...) — дельта может быть отрицательной
            $this->addForUser($user, $productId, $delta, $price, $meta);
        } else {
            $this->addToSession($productId, $delta, $price, $meta);
        }

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
        $user = auth()->user();

        if ($user) $this->setForUser($user, $productId, $qty, $price, $meta);
        else       $this->setInSession($productId, $qty, $price, $meta);

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
                Session::put(self::SESSION_KEY, array_values($items->all()));
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

        Session::put(self::SESSION_KEY, array_values($items->all()));
    }
    protected function setForUser($user, int $productId, int $qty, ?float $price, array $meta): void
    {
        $order = $this->getOrCreateDraftOrder($user, true);
        /** @var OrderItem $item */
        $item = $order->items()->firstOrNew(['product_id' => $productId]);

        if ($qty <= 0) {
            if ($item->exists) { $item->delete(); $this->recalc($order); }
            return;
        }

        $item->qty = $qty;
        if ($price !== null) $item->unit_price = $price;
        elseif (!$item->exists) $item->unit_price = $item->unit_price ?? 0;

        if (!empty($meta)) $item->meta = $meta;

        $item->save();
        $this->recalc($order);
    }
    protected function sessionTotal(): float
    {
        return (float)collect(Session::get(self::SESSION_KEY, []))
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

        Session::put(self::SESSION_KEY, array_values($items->all()));
    }

    protected function getOrCreateDraftOrder(Authenticatable $user, bool $createIfMissing = true): ?Order
    {
        /** @var Order|null $order */
        $order = Order::query()
            ->where('clients_id', $user->getAuthIdentifier())
            ->where('status', OrderStatus::Cart)
            ->first();

        if (!$order && $createIfMissing) {
            $order = new Order();
            $order->clients_id  = $user->getAuthIdentifier();
            $order->status      = OrderStatus::Cart;
            $order->total_price = 0;
            $order->save();
        }

        // если не нашли и не создавали — вернётся null
        return $order?->fresh(['items']);
    }


    protected function recalc(Order $order): void
    {
        $total = $order->items()->sum(DB::raw('qty * unit_price'));
        $order->total_price = $total;
        $order->save();
    }

    // --------------------------------------------------------------------
    //                         ITEMS (для мини-корзины)
    // --------------------------------------------------------------------

    public function items(): array
    {
        $user = auth()->user();

        // вспомогалка: строки атрибутов из варианта (размер/вес и т.д.)
        $variantAttrs = function ($product) {
            if (! $product || ! method_exists($product, 'productCharacteristicValues')) {
                return '';
            }

            $vals = $product->productCharacteristicValues()
                ->with(['characteristic:id,slug', 'characteristicValue'])
                ->get();

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

            return implode(' · ', array_filter($parts));
        };

        if ($user) {
            $order = $this->getOrCreateDraftOrder($user, false);
            $order->load(['items.product.parent']);

            return $order->items->map(function (\App\Models\Shop\OrderItem $it) use ($variantAttrs) {
                $p      = $it->product;                 // это вариант (child) либо сам parent
                $parent = $p?->parent ?: $p;            // если есть родитель — берём его

                $name  = $parent?->display_name ?? $parent?->displayName ?? $parent?->title ?? 'Товар';
                $sku   = $parent?->sku ?: null;
                $code2 = $parent?->code2 ?: null;
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
                    'meta'       => $it->meta ?? [],
                    'code2'      => $code2,
                ];
            })->values()->all();
        }

        // Гость — из сессии + подгружаем продукты и родителей
        $rows = collect(Session::get(self::SESSION_KEY, []));
        if ($rows->isEmpty()) return [];

        $ids = $rows->pluck('product_id')->all();

        /** @var \App\Models\Shop\Product $prodModel */
        $prodModel = app(\App\Models\Shop\Product::class);

        $products = $prodModel->newQuery()
            ->with(['parent'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return $rows->map(function ($i) use ($products, $variantAttrs) {
            $p      = $products->get((int)$i['product_id']); // child или parent
            $parent = $p?->parent ?: $p;

            $name  = $parent?->display_name ?? $parent?->displayName ?? $parent?->title ?? 'Товар';
            $sku   = $parent?->sku ?: null;
            $code2 = $parent?->code2 ?: null;
            $image = $parent?->main_image_url ?? ($parent?->image_url ?? null);

            $qty   = (int)($i['qty'] ?? 1);
            $price = (float)($i['price'] ?? 0);

            return [
                'product_id' => (int)($i['product_id'] ?? 0),
                'name'       => (string) $name,
                'sku'        => $sku,
                'image'      => $image,
                'variant'    => $variantAttrs($p),
                'qty'        => $qty,
                'price'      => $price,
                'subtotal'   => $qty * $price,
                'meta'       => $i['meta'] ?? [],
                'code2'      => $code2,
            ];
        })->values()->all();
    }

}
