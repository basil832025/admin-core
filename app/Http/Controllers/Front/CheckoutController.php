<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\LoyaltyService;
use App\Models\Shop\Order;
use App\Models\Shop\ClientAddress;
use App\Enums\OrderStatus;
use App\Models\Location;
use App\Enums\PaymentMethodEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Shop\PromoCode;
use App\Services\OrderPricing;
use App\Models\Shop\OrderAdjustment;
use App\Models\Shop\OrderItem;
use App\Models\Shop\Product;
use App\Models\Shop\FixedDiscount;
use App\Models\Shop\TimeDiscount;
use App\Services\LiqPayService;


class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly LoyaltyService $loyalty,
    ) {}

/**
 * Страница оформления заказа.
 * Здесь считаем доступные бонусы и прокидываем в $totals для _summary.blade.php
 */
public function index()
{
    $items  = $this->cart->items();
    $info   = $this->cart->info();
    // 1) Точки самовывоза из bs_locations
    $locations = Location::query()
        ->where('is_active', 1)
        ->orderBy('sort')
        ->get();
    // Сумма товаров и скидка — подстрой под свою структуру info()
    $itemsTotal = (float)($info['items_total'] ?? $info['total_price'] ?? 0);
    $discount   = (float)($info['discount'] ?? 0);

    $client   = Auth::user();          // твоя модель клиента
    $clientId = $client?->id;
    $phone    = $client?->phone ?? null;

    $balance = $this->loyalty->getBalance($clientId, $phone);
    $limit   = $this->loyalty->getBonusLimitForOrder($itemsTotal, $discount, $balance);
    // 👇 база для начисления и теоретические бонусы
    $bonusEarn = $this->loyalty->previewEarnForCart($itemsTotal, $discount);

    $totals = [
        'qty'          => (int)($info['qty'] ?? 0),
        'items_total'  => $itemsTotal,
        'discount'     => $discount,
        'grand_total'  => max($itemsTotal - $discount, 0),
        'bonus_points' => $balance,
        'bonus_limit'  => $limit,
        'bonus_earn'   => $bonusEarn,
        'bonus_used'   => 0,
    ];
    $productIds  = collect($items)->pluck('product_id');

    // --- ФИКСИРОВАННЫЕ АКЦИИ (типа "Именинникам") ---
    $fixedDiscounts = FixedDiscount::query()
        ->where('is_active', true)
        ->forAll()                   // твой scopeForAll()
        ->get()
        ->filter(function (FixedDiscount $d) use ($clientId, $productIds) {
            return $d->canApply($clientId) && $d->hasEligibleProducts($productIds);
        });

    // --- АКЦИИ ПО ВРЕМЕНИ ---
    $timeDiscounts = TimeDiscount::query()
        ->activeForMoment(now())    // твой scopeActiveForMoment()
        ->get()
        ->filter(function (TimeDiscount $d) use ($productIds) {
            return $d->hasEligibleProducts($productIds);
        });
// локаль сайта
    $locale = app()->getLocale();
    // приводим к единому массиву для шаблона
    $availablePromos = collect()
        ->merge(
            $fixedDiscounts->map(function (FixedDiscount $d) use ($locale) {
                // берём название по локали
                $name = $d->getTranslation('name', $locale);
                $p    = number_format((float)$d->percent, 2, '.', '');

                return [
                    'id'          => $d->id,
                    'type'        => 'fixed',
                    // Например: "Birthday 20% (−20.00%)"
                    'label'       => "{$name} (−{$p}%)",
                    'description' => $d->description ?? null,
                ];
            })
        )
        ->merge(
            $timeDiscounts->map(function (TimeDiscount $d) use ($locale) {
                $name = $d->getTranslation('name', $locale);
                $p    = number_format((float)$d->percent, 2, '.', '');

                return [
                    'id'          => $d->id,
                    'type'        => 'time',
                    // Например: "Happy hours 50%"
                    'label'       => "{$name} ({$p}%)",
                    'description' => $d->description ?? null,
                ];
            })
        );
    return view('checkout.index', [
        'items'     => $items,
        'totals'    => $totals,
        'locations' => $locations,
        'availablePromos' => $availablePromos,

    ]);
}
public function updatePromo(Request $request)
{
    $client    = auth()->user();
    $selection = (string) $request->input('promo', 'none');
    // если гость — не даём применять акцию, только после логина
    if (! $client) {
        // запомним выбор как 'none', чтобы не тащился в сессию
        session(['checkout.selected_promo' => 'none']);

        return response()->json([
            'ok'            => false,
            'requires_auth' => true,
            'message'       => 'Щоб застосувати акцію, увійдіть або зареєструйтесь.',
        ]);
    }
    // запомним выбор в сессию
    session(['checkout.selected_promo' => $selection]);

    // --- 1. Пытаемся найти черновик заказа (Cart) для клиента ---
    $order = null;

    if ($client) {
        $order = Order::where('clients_id', $client->id)
            ->where('status', OrderStatus::Cart)
            ->latest('id')
            ->first();
    }

    if ($order) {
        // чтобы calculateAmountForOrder учитывал категории и характеристики
        $order->loadMissing(['items.product.categories']);
        if (method_exists(Product::class, 'attributeValues')) {
            $order->loadMissing(['items.product.attributeValues']);
        }

        $baseTotal = (float) $order->total_price;
    } else {
        // гость / нет заказа -> берём сумму из CartService
        $info      = $this->cart->info();
        $baseTotal = (float) ($info['total_price'] ?? 0);
    }

    $discount = 0.0;
    $total    = $baseTotal;

    // --- 2. Считаем скидку по выбранной акции ---
    if ($selection !== 'none') {
        [$kind, $id] = explode(':', $selection) + [null, null];

        $promo = null;

        if ($kind === 'fixed') {
            $promo = FixedDiscount::query()
                ->where('is_active', true)
                ->find($id);
        } elseif ($kind === 'time') {
            $promo = TimeDiscount::query()
                ->activeForMoment(now())
                ->find($id);
        }

        if ($promo) {
            if ($order) {
                // ТОЧНАЯ логика: как в админке
                $amount = (float) $promo->calculateAmountForOrder($order); // отрицательное

                if ($amount < 0) {
                    $discount = abs($amount);
                    $total    = max(0, $baseTotal - $discount);
                }
            } else {
                // нет заказа (гость) -> примитивный вариант, как было раньше
                $discount = (float) $promo->calculateForTotal($baseTotal);
                $total    = max(0, $baseTotal - $discount);
            }
        }
    }

    // --- 3. Форматирование для фронта ---
    $uah = (int) floor($total);
    $kop = (int) round(($total - $uah) * 100);

    return response()->json([
        'ok'        => true,
        'selection' => $selection,

        'discount'  => $discount,
        'total'     => $total,

        'discount_formatted' => number_format($discount, 2, ',', ' ') . ' грн',
        'total_formatted'    => number_format($total, 2, ',', ' ') . ' грн',

        'total_uah'          => $uah,
        'total_uah_formatted'=> number_format($uah, 0, ',', ' '),
        'total_kop'          => sprintf('%02d', $kop),
    ]);
}
public function applyCoupon(Request $request)
{
    try {
        $code = trim((string) $request->input('coupon', ''));

        if ($code === '') {
            return response()->json([
                'ok'   => false,
                'mess' => 'Введите промокод',
            ]);
        }

        // 1) ищем активный промокод
        $promo = PromoCode::query()
            ->active()
            ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
            ->first();

        if (! $promo) {
            return response()->json([
                'ok'   => false,
                'mess' => 'Промокод не найден или не активен',
            ]);
        }

        $client   = auth()->user();
        $clientId = $client?->id;

        // 2) лимиты/доступность для клиента
        if (! $promo->canApplyForClient($clientId)) {
            return response()->json([
                'ok'   => false,
                'mess' => 'Этот промокод сейчас нельзя применить',
            ]);
        }

        // 3) берём текущие позиции из корзины
        $cartItems = $this->cart->items();
        if (empty($cartItems)) {
            return response()->json([
                'ok'   => false,
                'mess' => 'Корзина пуста',
            ]);
        }

        // 4) собираем ВИРТУАЛЬНЫЙ заказ:
        // превращаем элементы корзины в OrderItem-модели с привязанным Product
        $items = collect($cartItems)->map(function ($row) {
            $item = new OrderItem();

            if (is_array($row)) {
                $product = $row['product'] ?? null;

                $item->qty        = $row['qty'] ?? $row['quantity'] ?? 1;
                $item->unit_price = $row['unit_price'] ?? $row['price'] ?? 0;
                $item->meta       = $row['meta'] ?? [];
                $item->product_id = $row['product_id'] ?? ($product?->id);
            } else {
                // объект из CartService
                $product = $row->product ?? null;

                $item->qty        = $row->qty ?? 1;
                $item->unit_price = $row->unit_price ?? $row->price ?? 0;
                $item->meta       = $row->meta ?? [];
                $item->product_id = $row->product_id ?? ($product?->id);
            }

            if ($product instanceof Product) {
                $item->setRelation('product', $product);
            }

            return $item;
        });

        $order = new Order();
        $order->setRelation('items', $items);

        // 5) считаем скидку через уже готовый метод
        $amount = (float) $promo->calculateAmountForOrder($order);

        if ($amount >= 0.0) {
            return response()->json([
                'ok'   => false,
                'mess' => 'Промокод не даёт скидки для текущих товаров',
            ]);
        }

        return response()->json([
            'ok'       => true,
            'code'     => $promo->code,
            'discount' => round(abs($amount), 2),
            'message'  => 'Промокод застосовано',
        ]);

    } catch (\Throwable $e) {
        \Log::error('applyCoupon error: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'ok'   => false,
            'mess' => 'Внутренняя ошибка: '.$e->getMessage(),
        ]);
    }
}





public function submit(Request $request)
{
    $client = auth()->user();
    $couponCode = trim((string) $request->input('coupon', ''));
    // 1. Базовая валидация
    $validated = $request->validate([
        'contact_name'     => 'required|string|max:255',
        'contact_phone'    => 'required|string|max:50',
        'contact_email'    => 'nullable|email|max:255',

        'shipping_method'  => 'required|in:delivery,pickup',
        'delivery_mode'    => 'required|in:asap,fixed',

        'payment_method'   => 'required|in:liqpay,card_on_delivery,cash',

        'agree'            => 'accepted',
    ]);

    // 2. Адрес: существующий или новый
    $useNew = $request->boolean('use_new_address')
        || ! $client
        || ($client && ! $client->addresses()->exists());

    $addressId = null;
    $addressSnapshot = null;

    if ($useNew) {
        $addr = $request->validate([
            'addr.street'           => 'required|string|max:255',
            'addr.house'            => 'required|string|max:50',
            'addr.apartment'        => 'nullable|string|max:50',
            'addr.intercom'         => 'nullable|string|max:50',
            'addr.floor'            => 'nullable|string|max:20',
            'addr.porch'            => 'nullable|string|max:20',
            'addr.comment'          => 'nullable|string|max:500',
            'addr.is_private_house' => 'nullable|boolean',
            'addr.type'             => 'nullable|string|in:home,work,friends',
        ]);

        $addrData = [
            'client_id'       => $client?->id,
            'street'           => $addr['addr']['street'],
            'house'            => $addr['addr']['house'],
            'apartment'        => $addr['addr']['apartment'] ?? null,
            'intercom'         => $addr['addr']['intercom'] ?? null,
            'floor'            => $addr['addr']['floor'] ?? null,
            'entrance'         => $addr['addr']['porch'] ?? null,
            'comment'          => $addr['addr']['comment'] ?? null,
            'is_private_house' => !empty($addr['addr']['is_private_house']),
            'type'             => $addr['addr']['type'] ?? null,
            'city'             => 'Київ',
        ];

        $address   = ClientAddress::create($addrData);
        $addressId = $address->id;
        $addressSnapshot = $addrData;
    } else {
        $addressId = $request->input('selected_address_id');

        if ($client) {
            $address = $client->addresses()
                ->whereKey($addressId)
                ->first();
        } else {
            $address = ClientAddress::find($addressId);
        }

        if (! $address) {
            return back()
                ->withErrors(['selected_address_id' => 'Оберіть адресу доставки або введіть нову.'])
                ->withInput();
        }

        $addressId       = $address->id;
        $addressSnapshot = $address->only([
            'street', 'house', 'apartment', 'intercom', 'floor',
            'entrance', 'comment', 'is_private_house', 'type', 'city',
        ]);
    }

    // 3. Доставка (asap / fixed)

    $shippingMethod = $request->input('shipping_method');
    $deliveryMode   = $request->input('delivery_mode', 'asap');
    $deliveryDate   = $request->input('delivery_date');
    $deliveryTime   = $request->input('delivery_time');

    if ($deliveryMode === 'fixed') {
        $request->validate([
            'delivery_date' => 'required|string|max:50',
            'delivery_time' => 'required|string|max:50',
        ]);
    }

    // 4. Способ оплаты -> enum
    $paymentEnum = match ($request->input('payment_method')) {
        'liqpay'          => PaymentMethodEnum::LIQPAY,
        'card_on_delivery'=> PaymentMethodEnum::CARD,
        'cash'            => PaymentMethodEnum::CASH,
        default           => PaymentMethodEnum::CASH,
    };

    // 5. Контактные данные можно сразу сохранить в клиента
    if ($client) {
        $client->fill([
            'name'  => $validated['contact_name'],
            'phone' => $validated['contact_phone'],
            'email' => $validated['contact_email'] ?? $client->email,
        ])->saveQuietly();
    }

    // 6. Ищем черновик заказа клиента, если есть
    $order = null;
    if ($client) {
        $order = Order::where('clients_id', $client->id)
            ->where('status', OrderStatus::Cart)
            ->latest('id')
            ->first();
    }

    $cartInfo = $this->cart->info();

    // === 6.1. БОНУСЫ: считаем баланс, лимит и желаемое списание ===

    // Сколько сейчас товаров на сумму
    $itemsTotal = (float)($cartInfo['total_price'] ?? 0);
    // Пока других скидок нет — 0. Если потом появятся промокоды,
    // сюда можно подставить сумму скидки.
    $discountBase = 0.0;

    // Для лояльности берём client_id + телефон.
    $loyaltyClientId = $client?->id;
    $loyaltyPhone    = $client?->phone ?? $validated['contact_phone'];

    $balance = $this->loyalty->getBalance($loyaltyClientId, $loyaltyPhone);
    $limit   = $this->loyalty->getBonusLimitForOrder($itemsTotal, $discountBase, $balance);

    $useBonus       = $request->boolean('use_bonus');
    $requestedBonus = $useBonus ? (float)$request->input('bonus_amount', 0) : 0.0;
    $requestedBonus = max(0, min($requestedBonus, $limit)); // защита от «накрутки» из формы

    // Комментарии
    $commentKitchen = trim((string)$request->input('comment_kitchen', ''));
    $commentCourier = trim((string)$request->input('comment_courier', ''));
    $notesParts = [];
    if ($commentKitchen !== '') {
        $notesParts[] = 'Кухня: '.$commentKitchen;
    }
    if ($commentCourier !== '') {
        $notesParts[] = 'Курьер: '.$commentCourier;
    }

    $order->self_pickup = $shippingMethod === 'pickup' ? 1 : 0;

    // 7. Заполняем заказ (пока без учёта бонусов, только базовые суммы)
    $order->fill([
        'short_name'        => $validated['contact_name'],
        'client_address_id' => $addressId,
        'address'           => $addressSnapshot,

        'shipping_method'   => $validated['shipping_method'],
        'self_pickup'       => $validated['shipping_method'] === 'pickup',
        'as_soon_possible'  => $deliveryMode === 'asap',

        'payment'           => $paymentEnum,

        'notes'             => implode(' | ', $notesParts) ?: null,

        'total_price'       => $itemsTotal,
        'shipping_price'    => $order->shipping_price ?? 0,
    ]);

    $order->dat        = now()->toDateString();       // yyyy-mm-dd
    $order->time_start = now()->format('H:i');        // если поле у тебя есть

    // === ДАТА ДОСТАВКИ ===
    if ($deliveryMode === 'fixed' && $deliveryDate) {
        try {
            $date = Carbon::createFromFormat('d.m.Y', $deliveryDate);
            $order->date_order = $date->toDateString();
        } catch (\Throwable $e) {
            $order->date_order = now()->toDateString();
        }
    } else {
        // если пользователь не выбрал дату — авто: сегодня
        $order->date_order = now()->toDateString();
    }

    // === ВРЕМЯ ДОСТАВКИ ===
    if ($deliveryMode === 'fixed' && $deliveryTime) {
        // ожидаем формат "HH:MM" от клиента
        $order->time_order = $deliveryTime;
    } else {
        if (empty($order->time_order)) {
            $order->time_order = now()->addMinutes(60)->format('H:i');
        }
    }

    if ($paymentEnum === PaymentMethodEnum::LIQPAY) {
        // можно оставить Cart как статус ожидания оплаты,
        // либо сделать отдельное значение в enum (например, AwaitingPayment)
        $order->status = OrderStatus::Cart;
    } else {
        $order->status = OrderStatus::New;
    }

    $order->save();

    // 8. Для гостя переносим позиции из сессии в заказ
    if (auth()->guest() && method_exists($this->cart, 'storeItemsInOrder')) {
        $this->cart->storeItemsInOrder($order);
    }

    // Пересчёт суммы по связям (items + modifiers)
    $order->recalculateTotalPrice();
    // === 8.1 Применяем выбранную акцию (fixed/time) ===
    $selection = $request->input('selected_promo', session('checkout.selected_promo', 'none'));

    // на всякий случай убираем старые фикс/тайм-скидки, если они есть
    $order->adjustments()
        ->whereIn('type', ['fixed', 'time'])
        ->delete();

    if ($selection && $selection !== 'none') {
        [$kind, $id] = explode(':', $selection) + [null, null];

        if ($kind === 'fixed') {
            $promo = FixedDiscount::query()
                ->where('is_active', true)
                ->find($id);

            if ($promo) {
                // если в расчёте учитываются категории/характеристики — подгружаем связи
                $order->loadMissing(['items.product.categories']);
                if (method_exists(Product::class, 'attributeValues')) {
                    $order->loadMissing(['items.product.attributeValues']);
                }

                // ЭТОТ метод уже есть в модели и возвращает отрицательную сумму (как купон)
                $amount = (float) $promo->calculateAmountForOrder($order); // например, -345.00

                if ($amount < 0) {
                    $discount = abs($amount);

                    OrderAdjustment::create([
                        'shop_order_id' => $order->id,
                        'type'          => 'fixed',
                        'label'         => $promo->name,
                        'amount'        => $amount, // отрицательное значение!
                        'promotion_id'  => $promo->id,
                        'meta'          => [
                            'id'   => $promo->id,
                            'name' => $promo->name,
                        ],
                    ]);

                    // обновляем sale_sum и total_price_sale так же, как для купона/бонусов
                    $currentSale = (float) ($order->sale_sum ?? 0);
                    $order->sale_sum         = $currentSale + $discount;
                    $order->total_price_sale = max(0, $order->total_price - $order->sale_sum);
                    $order->save();
                }
            }

        } elseif ($kind === 'time') {
            $promo = TimeDiscount::query()
                ->activeForMoment(now())
                ->find($id);

            if ($promo) {
                $order->loadMissing(['items.product.categories']);
                if (method_exists(Product::class, 'attributeValues')) {
                    $order->loadMissing(['items.product.attributeValues']);
                }

                $amount = (float) $promo->calculateAmountForOrder($order); // отрицательное

                if ($amount < 0) {
                    $discount = abs($amount);

                    OrderAdjustment::create([
                        'shop_order_id' => $order->id,
                        'type'          => 'time',
                        'label'         => $promo->name,
                        'amount'        => $amount,
                        'promotion_id'  => $promo->id,
                        'meta'          => [
                            'id'   => $promo->id,
                            'name' => $promo->name,
                        ],
                    ]);

                    $currentSale = (float) ($order->sale_sum ?? 0);
                    $order->sale_sum         = $currentSale + $discount;
                    $order->total_price_sale = max(0, $order->total_price - $order->sale_sum);
                    $order->save();
                }
            }
        }
    }

// === 8.1 Применяем промокод (если был введён) ===
    if ($couponCode !== '') {
        try {
            $promo = PromoCode::query()
                ->active()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($couponCode)])
                ->first();

            if ($promo && $promo->canApplyForClient($client?->id)) {
                // подгружаем нужные связи для расчёта
                $order->loadMissing(['items.product.categories']);
                if (method_exists(Product::class, 'attributeValues')) {
                    $order->loadMissing(['items.product.attributeValues']);
                }

                // считаем скидку для ЭТОГО заказа
                $amount = (float) $promo->calculateAmountForOrder($order); // ОТРИЦАТЕЛЬНОЕ число

                if ($amount < 0) {
                    $discount = abs($amount);

                    // на всякий случай удаляем все старые купонные скидки
                    $order->adjustments()
                        ->where('type', 'coupon')
                        ->delete();

                    // создаём одну запись в bs_shop_order_adjustments
                    OrderAdjustment::create([
                        'shop_order_id' => $order->id,
                        'type'          => 'coupon',
                        'label'         => 'Промокод ' . $promo->code,
                        'amount'        => $amount, // отрицательное значение
                        'meta'          => [
                            'code'          => $promo->code,
                            'promo_code_id' => $promo->id,
                        ],
                    ]);

                    // обновляем суммарную скидку и итог по заказу
                    $currentSale = (float) ($order->sale_sum ?? 0);
                    $order->sale_sum         = $currentSale + $discount;
                    $order->total_price_sale = max(0, $order->total_price - $order->sale_sum);
                    // если у заказа есть поле promo_code — сохраним код
                    if ($order->isFillable('promo_code')) {
                        $order->promo_code = $promo->code;
                    }
                    $order->save();

                    // отмечаем использование промокода (по order_id будет только одна запись)
                    $promo->markUsed($client?->id, $order->id);
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Checkout promo apply error: '.$e->getMessage(), [
                'order_id' => $order->id,
                'coupon'   => $couponCode,
            ]);
        }
    }

// === 9. Реальное списание бонусов по заказу ===
    if ($requestedBonus > 0) {
        $used = $this->loyalty->spendOnOrder($order, $requestedBonus);

        if ($used > 0) {
            $currentSaleSum = (float)$order->sale_sum;

            $order->sale_sum         = $currentSaleSum + $used;
            $order->total_price_sale = max(0, $order->total_price - $order->sale_sum);
            $order->save();
        }
    }

// === 9.1 Финальный пересчёт grand_total для Filament ===
    $baseTotal = (float) $order->total_price;
    $adjTotal  = (float) $order->adjustments()->sum('amount'); // купоны, ручные скидки и т.п.

    $order->grand_total = max(0, round($baseTotal + $adjTotal, 2));
    $order->save();

// 10. Очищаем только гостевую корзину
    if (method_exists($this->cart, 'clearAfterCheckout')) {
        $this->cart->clearAfterCheckout();
    }
    session()->forget('checkout.selected_promo');

    if ($paymentEnum === PaymentMethodEnum::LIQPAY) {
        // своя страница с кнопкой LiqPay
        return redirect()->route('checkout.pay.liqpay', $order);
    }

    return redirect()->route('checkout.success', $order);

}

public function success(Order $order)
{
    // защита от чужих заказов
    if (auth()->check() && $order->clients_id && auth()->id() !== $order->clients_id) {
        abort(403);
    }
  //  dd($order);
    // сразу подгружаем items + product
   // $order->load('items.product');

   // $items = $order->items;
 //   dd($items);
    // подгружаем позиции и связанные товары
    //$items = $order->items()->with('product')->get();
    $order->load(['items.product.parent']);   // имя связи parent можно поменять, см. ниже

    $items = $order->items;
  //  $info   = $this->cart->info();

   // dd($items);
    return view('checkout.success', compact('order', 'items'));
}


public function payLiqPay(Order $order)
{
    // защита от "чужих" заказов
    // Если заказ привязан к клиенту - проверяем, что текущий пользователь это его владелец
    if ($order->clients_id) {
        // Заказ привязан к клиенту - проверяем авторизацию и владельца
        // Используем строгое сравнение с приведением типов
        $orderClientId = (int) $order->clients_id;
        $currentUserId = auth()->check() ? (int) auth()->id() : null;
        
        if (!$currentUserId || $currentUserId !== $orderClientId) {
            abort(403);
        }
    }
    // Для гостевых заказов (clients_id = null) разрешаем доступ всем

    if ($order->payment !== PaymentMethodEnum::LIQPAY) {
        abort(404);
    }

    $liqpayForm = LiqPayService::make()->formForOrder($order);

    return view('checkout.liqpay', [
        'order'     => $order,
        'liqpayForm'=> $liqpayForm,
    ]);
}

}
