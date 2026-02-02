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
//use App\Models\Shop\FixedDiscount;
use App\Models\Shop\TimeDiscount;
use App\Services\LiqPayService;
use App\Mail\OrderNotificationMail;
use App\Mail\OrderClientMail;
use Illuminate\Support\Facades\Mail;


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
    // Проверяем авторизацию - если не авторизован, перенаправляем на страницу авторизации
    if (!Auth::check()) {
        // Сохраняем URL checkout в сессии для редиректа после авторизации
        session(['checkout.redirect_url' => request()->url()]);

        return redirect()->route('auth.show');
    }

    // Загружаем сохраненные данные из сессии
    $sessionData = session('checkout.form_data', []);

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
 /*   $fixedDiscounts = FixedDiscount::query()
        ->where('is_active', true)
        ->forAll()                   // твой scopeForAll()
        ->get()
        ->filter(function (FixedDiscount $d) use ($clientId, $productIds) {
            return $d->canApply($clientId) && $d->hasEligibleProducts($productIds);
        });*/

    // --- АКЦИИ ПО ВРЕМЕНИ ---
    $timeDiscounts = TimeDiscount::query()
        ->activeForMoment(now())    // твой scopeActiveForMoment()
        ->get()
        ->filter(function (TimeDiscount $d) use ($productIds) {
            return $d->hasEligibleProducts($productIds);
        });

    // Генерируем временные интервалы для доставки
    $timeIntervals = $this->getDeliveryTimeIntervals($locations);

// локаль сайта
    $locale = app()->getLocale();
    // приводим к единому массиву для шаблона
    $availablePromos = collect()
   /*     ->merge(
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
        )*/
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
        'sessionData' => $sessionData,
        'timeIntervals' => $timeIntervals,
    ]);
}

/**
 * Получить временные интервалы для доставки из графика работы
 *
 * @param \Illuminate\Support\Collection $locations
 * @return array Массив интервалов вида ["09:00-09:15", "09:15-09:30", ...]
 */
private function getDeliveryTimeIntervals($locations): array
{
    $startTime = '08:30';
    $endTime = '21:00';

    // Пытаемся найти график доставки во всех активных точках
    if ($locations->isNotEmpty()) {
        foreach ($locations as $location) {
            $schedule = $location->schedule ?? null;


            if (is_array($schedule) && !empty($schedule)) {
                // Ищем элемент со slug = "delivery"
                foreach ($schedule as $index => $scheduleItem) {
                    // Filament Repeater создает структуру с 'data' и верхним уровнем
                    // Проверяем slug на верхнем уровне (обрезаем пробелы)
                    $slug = trim($scheduleItem['slug'] ?? '');
                    if ($slug !== 'delivery') {
                        continue;
                    }

                    // Проверяем, активен ли график (сначала верхний уровень, потом data)
                    $isActive = $scheduleItem['is_active'] ?? ($scheduleItem['data']['is_active'] ?? true);
                    if ($isActive === false) {
                        continue;
                    }

                    // Получаем время из украинского перевода по умолчанию
                    // Сначала проверяем верхний уровень (там правильное значение)
                    $timeData = $scheduleItem['time'] ?? $scheduleItem['data']['time'] ?? null;
                    $timeStr = null;

                    // time может быть массивом локализованных значений или строкой
                    // Filament может создавать двойную вложенность: time[uk][uk]
                    if (is_array($timeData)) {
                        // Сначала пробуем двойную вложенность для uk (time[uk][uk])
                        if (isset($timeData['uk']['uk']) && is_string($timeData['uk']['uk'])) {
                            $timeStr = $timeData['uk']['uk'];
                        } elseif (isset($timeData['uk']) && is_string($timeData['uk'])) {
                            // Прямой доступ time[uk]
                            $timeStr = $timeData['uk'];
                        }
                    } elseif (is_string($timeData)) {
                        $timeStr = $timeData;
                    }

                    if ($timeStr) {
                        // Парсим строку вида "з 09:00 до 21:00", "09:00-21:00", "с 09:00 до 21:00"
                        // Ищем два времени в формате HH:MM
                        if (preg_match_all('/(\d{1,2}):(\d{2})/u', $timeStr, $timeMatches, PREG_SET_ORDER)) {
                            if (count($timeMatches) >= 2) {
                                // Есть два времени - это начало и конец
                                $startTime = sprintf('%02d:%02d', (int)$timeMatches[0][1], (int)$timeMatches[0][2]);
                                $endTime = sprintf('%02d:%02d', (int)$timeMatches[1][1], (int)$timeMatches[1][2]);
                                // Нашли нужный график, выходим из всех циклов
                                break 2;
                            } elseif (count($timeMatches) === 1) {
                                // Если найдено только одно время, используем как начальное
                                $startTime = sprintf('%02d:%02d', (int)$timeMatches[0][1], (int)$timeMatches[0][2]);
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }

    // Генерируем интервалы по 15 минут
    $intervals = [];
    $start = $this->timeToMinutes($startTime);
    $end = $this->timeToMinutes($endTime);

    for ($current = $start; $current < $end; $current += 15) {
        $time1 = $this->minutesToTime($current);
        $time2 = $this->minutesToTime($current + 15);
        $intervals[] = "{$time1}-{$time2}";
    }

    return $intervals;
}

/**
 * Конвертировать время в минуты с начала дня
 */
private function timeToMinutes(string $time): int
{
    [$hours, $minutes] = explode(':', $time);
    return (int)$hours * 60 + (int)$minutes;
}

/**
 * Конвертировать минуты в формат HH:MM
 */
private function minutesToTime(int $minutes): string
{
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

/**
 * Сохранение данных формы в сессию
 */
public function saveFormData(Request $request)
{
    $data = [];

    // Контактные данные
    if ($request->has('contact_name')) {
        $data['contact_name'] = $request->input('contact_name');
    }
    if ($request->has('contact_phone')) {
        $data['contact_phone'] = $request->input('contact_phone');
    }
    if ($request->has('contact_email')) {
        $data['contact_email'] = $request->input('contact_email');
    }

    // Способ получения и адрес
    if ($request->has('shipping_method')) {
        $data['shipping_method'] = $request->input('shipping_method');
    }
    if ($request->has('selected_address_id')) {
        $data['selected_address_id'] = $request->input('selected_address_id');
    }
    if ($request->has('use_new_address')) {
        $data['use_new_address'] = $request->boolean('use_new_address');
    }

    // Данные нового адреса
    if ($request->has('addr_street')) {
        $data['addr_street'] = $request->input('addr_street');
    }
    if ($request->has('addr_house')) {
        $data['addr_house'] = $request->input('addr_house');
    }
    if ($request->has('addr_apartment')) {
        $data['addr_apartment'] = $request->input('addr_apartment');
    }
    if ($request->has('addr_intercom')) {
        $data['addr_intercom'] = $request->input('addr_intercom');
    }
    if ($request->has('addr_floor')) {
        $data['addr_floor'] = $request->input('addr_floor');
    }
    if ($request->has('addr_porch')) {
        $data['addr_porch'] = $request->input('addr_porch');
    }
    if ($request->has('addr_comment')) {
        $data['addr_comment'] = $request->input('addr_comment');
    }
    if ($request->has('addr_is_private_house')) {
        $data['addr_is_private_house'] = $request->boolean('addr_is_private_house');
    }
    if ($request->has('addr_type')) {
        $data['addr_type'] = $request->input('addr_type');
    }
    if ($request->has('delivery_zone')) {
        $data['delivery_zone'] = $request->input('delivery_zone');
    }
    if ($request->has('shipping_price')) {
        $data['shipping_price'] = (float) $request->input('shipping_price');
    }
    // Условия доставки
    if ($request->has('delivery_mode')) {
        $data['delivery_mode'] = $request->input('delivery_mode');
    }
    if ($request->has('delivery_date')) {
        $data['delivery_date'] = $request->input('delivery_date');
    }
    if ($request->has('delivery_time')) {
        $data['delivery_time'] = $request->input('delivery_time');
    }

    // Способ оплаты
    if ($request->has('payment_method')) {
        $data['payment_method'] = $request->input('payment_method');
    }

    // Комментарии
    if ($request->has('comment_kitchen')) {
        $data['comment_kitchen'] = $request->input('comment_kitchen');
    }
    if ($request->has('comment_courier')) {
        $data['comment_courier'] = $request->input('comment_courier');
    }

    // Сохраняем в сессию (объединяем с существующими данными, чтобы не потерять уже сохраненные)
    $existingData = session('checkout.form_data', []);
    $mergedData = array_merge($existingData, $data);
    session(['checkout.form_data' => $mergedData]);

    // Обновляем черновик заказа, если он существует
    $client = Auth::user();
    if ($client) {
        $order = Order::where('clients_id', $client->id)
            ->where('status', OrderStatus::Cart)
            ->latest('id')
            ->first();

        if ($order) {
            // Обновляем дату и время доставки в черновике
            $deliveryMode = $mergedData['delivery_mode'] ?? 'asap';
            $deliveryDate = $mergedData['delivery_date'] ?? null;
            $deliveryTimeRaw = $mergedData['delivery_time'] ?? null;

            // Извлекаем первое время из диапазона
            $deliveryTime = $deliveryTimeRaw;
            if ($deliveryTimeRaw && strpos($deliveryTimeRaw, '-') !== false) {
                $deliveryTime = trim(explode('-', $deliveryTimeRaw)[0]);
            }

            // Обновляем дату доставки
            if ($deliveryMode === 'fixed' && $deliveryDate) {
                try {
                    // Пробуем разные форматы даты
                    // Формат от flatpickr altInput: d.m.Y (например, "21.01.2026")
                    if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $deliveryDate)) {
                        $date = Carbon::createFromFormat('d.m.Y', $deliveryDate);
                        $order->date_order = $date->toDateString();
                    }
                    // Формат от flatpickr dateFormat: Y-m-d (например, "2026-01-21")
                    elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $deliveryDate)) {
                        $date = Carbon::createFromFormat('Y-m-d', $deliveryDate);
                        $order->date_order = $date->toDateString();
                    } else {
                        // Пробуем автоматическое определение формата
                        $date = Carbon::parse($deliveryDate);
                        $order->date_order = $date->toDateString();
                    }
                } catch (\Throwable $e) {
                    // Оставляем текущую дату при ошибке парсинга
                    \Log::warning('Failed to parse delivery date in saveFormData', [
                        'date' => $deliveryDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($deliveryMode === 'asap') {
                // Если переключились на "как можно скорее", устанавливаем сегодняшнюю дату
                $order->date_order = now()->toDateString();
            }

            // Обновляем время доставки
            if ($deliveryMode === 'fixed' && $deliveryTime) {
                $order->time_order = $deliveryTime;
            } elseif ($deliveryMode === 'asap') {
                // Если переключились на "как можно скорее", устанавливаем время через час
                $order->time_order = now()->addMinutes(60)->format('H:i');
            }

            // Обновляем режим доставки
            $order->as_soon_possible = $deliveryMode === 'asap';
            // ✅ пишем стоимость доставки в черновик
            if (array_key_exists('shipping_price', $mergedData)) {
                $order->shipping_price = (float) $mergedData['shipping_price'];
            }

            // если самовывоз — доставка 0
            if (($mergedData['shipping_method'] ?? null) === 'pickup') {
                $order->shipping_price = 0;
            }
// ✅ 1) Способ получения (доставка/самовывоз)
            $shippingMethod = $mergedData['shipping_method'] ?? 'delivery';

// если у тебя в заказе есть поле self_pickup:
            $order->self_pickup = ($shippingMethod === 'pickup');

// ✅ 2) Стоимость доставки
            if (isset($mergedData['shipping_price'])) {
                $order->shipping_price = (float) $mergedData['shipping_price'];
            }

// ✅ 3) Выбранный сохранённый адрес
            $useNew = !empty($mergedData['use_new_address']);

            if ($shippingMethod === 'pickup') {
                // самовывоз — адрес не нужен, доставка 0
                $order->client_address_id = null;
                $order->shipping_price = 0;
            } else {
                if (! $useNew) {
                    // выбран сохранённый адрес
                    $addrId = $mergedData['selected_address_id'] ?? null;
                    $order->client_address_id = $addrId ? (int) $addrId : null;

                    // (необязательно, но полезно) сохранить "снимок" адреса в order->address, если такое поле есть

                } else {
                    // новый адрес — сохранённого id нет
                    $order->client_address_id = null;
                }
            }

            $order->save();
        }
    }

    return response()->json(['ok' => true]);
}
public function updatePromo(Request $request)
{
    $client    = auth()->user();
    $selection = (string) $request->input('promo', 'none');

    // если гость — не даём применять акцию, только после логина
    if (! $client) {
        session(['checkout.selected_promo' => 'none']);

        return response()->json([
            'ok'            => false,
            'requires_auth' => true,
            'message'       => 'Щоб застосувати акцію, увійдіть або зареєструйтесь.',
        ]);
    }

    // запомним выбор в сессию
    session(['checkout.selected_promo' => $selection]);

    // 1) находим Cart-заказ клиента
    $order = Order::where('clients_id', $client->id)
        ->where('status', OrderStatus::Cart)
        ->latest('id')
        ->first();

    if (! $order) {
        // нет черновика — просто вернём сумму корзины
        $info      = $this->cart->info();
        $baseTotal = (float) ($info['total_price'] ?? 0);

        return response()->json([
            'ok'        => true,
            'selection' => $selection,
            'discount'  => 0,
            'total'     => $baseTotal,
            'discount_formatted' => number_format(0, 2, ',', ' ') . ' грн',
            'total_formatted'    => number_format($baseTotal, 2, ',', ' ') . ' грн',
            'total_uah'          => (int) floor($baseTotal),
            'total_uah_formatted'=> number_format((int) floor($baseTotal), 0, ',', ' '),
            'total_kop'          => sprintf('%02d', (int) round(($baseTotal - floor($baseTotal)) * 100)),
        ]);
    }

    // 2) Применяем выбранную акцию ЕДИНОЙ логикой (как в админке)
    $pricing = app(\App\Services\OrderPricing::class);

    // чистим прошлые скидки fixed/time
    $order->adjustments()->whereIn('type', ['fixed', 'time'])->delete();

    if ($selection !== 'none') {
        [$kind, $id] = explode(':', $selection) + [null, null];
        $id = (int) $id;

        if ($kind === 'fixed') {
            $pricing->applyFixedExclusive($order, $id, 'single');
        } elseif ($kind === 'time') {
            $pricing->applyTimeExclusive($order, $id, 'single');
        } else {
            $pricing->recalc($order);
        }
    } else {
        $pricing->recalc($order);
    }

    // 3) Берём результат из БД (adjustments уже записаны)
    $discount = abs((float) $order->adjustments()
        ->whereNull('shop_order_item_id')
        ->whereIn('type', ['fixed', 'time'])
        ->sum('amount')); // amount отрицательный, поэтому abs()

    $total = (float) ($order->grand_total ?? 0);

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
        'delivery_time'    => 'nullable|string|max:20',

        'payment_method'   => 'required|in:liqpay,card_on_delivery,cash,invoice',

        'agree'            => 'accepted',
    ]);

    // 2. Условная валидация: если выбран режим "fixed", дата и время обязательны
    $deliveryMode = $request->input('delivery_mode', 'asap');
    if ($deliveryMode === 'fixed') {
        $request->validate([
            'delivery_date' => 'required|string|max:50',
            'delivery_time' => 'required|string|max:20',
        ], [
            'delivery_date.required' => st('cart.delivery.date_required', 'Оберіть дату доставки'),
            'delivery_time.required' => st('cart.delivery.time_required', 'Оберіть час доставки'),
        ]);
    }

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
            'addr.lat'              => 'nullable|numeric',
            'addr.lng'              => 'nullable|numeric',
        ]);

        $addrData = [
            'client_id'        => $client?->id,
            'street'           => $addr['addr']['street'],
            'house'            => $addr['addr']['house'],
            'apartment'        => $addr['addr']['apartment'] ?? null,
            'intercom'         => $addr['addr']['intercom'] ?? null,
            'floor'            => $addr['addr']['floor'] ?? null,
            'entrance'         => $addr['addr']['porch'] ?? null,
            'note'             => $addr['addr']['comment'] ?? null,
            'is_private_house' => !empty($addr['addr']['is_private_house']),
            'type'             => $addr['addr']['type'] ?? null,
            'city'             => 'Київ',
            // координаты из формы checkout (заполняются Google Autocomplete)
            'latitude'         => $addr['addr']['lat'] ?? null,
            'longitude'        => $addr['addr']['lng'] ?? null,
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
    $deliveryTimeRaw = $request->input('delivery_time'); // Диапазон типа "12:00-12:15"

    // Извлекаем первое время из диапазона для сохранения в базу
    // Если формат "12:00-12:15", берем "12:00"
    $deliveryTime = $deliveryTimeRaw;
    if ($deliveryTimeRaw && strpos($deliveryTimeRaw, '-') !== false) {
        $deliveryTime = trim(explode('-', $deliveryTimeRaw)[0]);
    }

    // Валидация даты и времени для режима "fixed" уже выполнена выше

    // 4. Способ оплаты -> enum
    $paymentEnum = match ($request->input('payment_method')) {
        'liqpay'          => PaymentMethodEnum::LIQPAY,
        'card_on_delivery'=> PaymentMethodEnum::CARD,
        'cash'            => PaymentMethodEnum::CASH,
        'invoice'         => PaymentMethodEnum::INVOICE,
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
        // Проверяем, что клиент существует в базе данных
        $clientExists = \App\Models\Shop\Client::where('id', $client->id)->exists();

        if ($clientExists) {
            $order = Order::where('clients_id', $client->id)
                ->where('status', OrderStatus::Cart)
                ->latest('id')
                ->first();
        } else {
            \Log::warning('Client not found when searching for draft order', [
                'client_id' => $client->id,
            ]);
        }
    }

    // Если заказа нет, создаем новый
    if (!$order) {
        $order = new Order();
        $order->status = OrderStatus::Cart;
        $order->total_price = 0;
        $order->currency = 'UAH';

        // Устанавливаем clients_id только если клиент существует
        if ($client) {
            $clientExists = \App\Models\Shop\Client::where('id', $client->id)->exists();
            $order->clients_id = $clientExists ? $client->id : null;
        } else {
            $order->clients_id = null; // Гостевой заказ
        }

        $order->save();
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
// === 8.1 Применяем выбранную акцию (fixed/time) через общий сервис
    $selection = $request->input('selected_promo', session('checkout.selected_promo', 'none'));

    $pricing = app(\App\Services\OrderPricing::class);

// чистим прошлые скидки fixed/time
    $order->adjustments()->whereIn('type', ['fixed', 'time'])->delete();

    if ($selection && $selection !== 'none') {
        [$kind, $id] = explode(':', $selection) + [null, null];
        $id = (int) $id;

        if ($kind === 'fixed') {
            $pricing->applyFixedExclusive($order, $id, 'single');
        } elseif ($kind === 'time') {
            $pricing->applyTimeExclusive($order, $id, 'single');
        } else {
            $pricing->recalc($order);
        }
    } else {
        $pricing->recalc($order);
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

    // 10. Отправляем уведомление на почту
    try {
        $order->load([
            'items.product.parent.productCharacteristicValues.characteristic.svgImage',
            'items.product.productCharacteristicValues.characteristic.svgImage',
            'items.product.productCharacteristicValues.characteristicValue',
            'adjustments',
            'clientAddress',
            'clients'
        ]);
        $notificationEmails = config('notifications.order_notification_email', []);
        // Если это строка (старый формат), преобразуем в массив
        if (is_string($notificationEmails)) {
            $notificationEmails = array_filter(array_map('trim', explode(',', $notificationEmails)));
        }
        // Если массив пустой, используем fallback
        if (empty($notificationEmails)) {
            $notificationEmails = ['info@3piroga.ua'];
        }

        if (!empty($notificationEmails)) {
            \Log::info('Sending order notification email', [
                'order_id' => $order->id,
                'emails' => $notificationEmails,
                'mail_driver' => config('mail.default'),
            ]);
            Mail::to($notificationEmails)->send(new OrderNotificationMail($order));
            \Log::info('Order notification email sent successfully', [
                'order_id' => $order->id,
                'emails' => $notificationEmails,
            ]);
        } else {
            \Log::warning('Order notification email not configured', ['order_id' => $order->id]);
        }
    } catch (\Throwable $e) {
        \Log::error('Failed to send order notification email: ' . $e->getMessage(), [
            'order_id' => $order->id,
            'email' => $notificationEmail ?? 'not configured',
            'mail_driver' => config('mail.default'),
            'trace' => $e->getTraceAsString(),
        ]);
    }

// 11. Очищаем только гостевую корзину
    if (method_exists($this->cart, 'clearAfterCheckout')) {
        $this->cart->clearAfterCheckout();
    }
    session()->forget('checkout.selected_promo');
    // Очищаем сохраненные данные формы после успешного оформления
    session()->forget('checkout.form_data');

    if ($paymentEnum === PaymentMethodEnum::LIQPAY) {
        // своя страница с кнопкой LiqPay
        return redirect()->route('checkout.pay.liqpay', $order);
    }

    return redirect()->route('checkout.success', $order);

}

public function success(Order $order)
{
    // защита от чужих заказов
    if ($order->clients_id) {
        $orderClientId = (int) $order->clients_id;
        $currentUserId = auth()->check() ? (int) auth()->id() : null;

        if (!$currentUserId || $currentUserId !== $orderClientId) {
            abort(403);
        }
    }
  //  dd($order);
    // сразу подгружаем items + product
   // $order->load('items.product');

   // $items = $order->items;
 //   dd($items);
    // подгружаем позиции и связанные товары
    //$items = $order->items()->with('product')->get();
    $order->load(['items.product.parent']);   // имя связи parent можно поменять, см. ниже

    // Проверяем, рабочее ли сейчас время (для отображения разного текста)
    $isWorkingHours = $this->isWorkingHours();

    // Получаем номер заказа
    $orderNumber = $order->number ?? str_pad($order->id, 5, '0', STR_PAD_LEFT);

    $items = $order->items;
  //  $info   = $this->cart->info();

   // dd($items);
    return view('checkout.success', compact('order', 'items', 'isWorkingHours', 'orderNumber'));
}

/**
 * Отправка заказа на email клиенту
 */
public function sendOrderToEmail(Request $request, Order $order)
{
    // Защита от чужих заказов
    if ($order->clients_id) {
        $orderClientId = (int) $order->clients_id;
        $currentUserId = auth()->check() ? (int) auth()->id() : null;

        if (!$currentUserId || $currentUserId !== $orderClientId) {
            abort(403);
        }
    }

    // Загружаем связь clients если не загружена
    if (!$order->relationLoaded('clients')) {
        $order->load('clients');
    }

    // Получаем email клиента
    $clientEmail = null;
    if ($order->clients && !empty($order->clients->email)) {
        $clientEmail = $order->clients->email;
    } elseif ($request->has('email') && filter_var($request->input('email'), FILTER_VALIDATE_EMAIL)) {
        $clientEmail = $request->input('email');
    } elseif (auth()->check() && auth()->user()->email) {
        // Если пользователь авторизован, используем его email
        $clientEmail = auth()->user()->email;
    }

    if (!$clientEmail) {
        return response()->json([
            'success' => false,
            'message' => st('order.email.no_email', 'Email не указан'),
        ], 400);
    }

    try {
        // Устанавливаем украинскую локаль для email клиенту
        $originalLocale = app()->getLocale();
        app()->setLocale('uk');

        \Log::info('Sending order email to client', [
            'order_id' => $order->id,
            'email' => $clientEmail,
            'locale' => app()->getLocale(),
            'mail_driver' => config('mail.default'),
        ]);

        Mail::to($clientEmail)->send(new OrderClientMail($order));

        // Восстанавливаем исходную локаль
        app()->setLocale($originalLocale);

        \Log::info('Order email sent to client successfully', [
            'order_id' => $order->id,
            'email' => $clientEmail,
        ]);

        return response()->json([
            'success' => true,
            'message' => st('order.email.sent_success', 'Замовлення відправлено на email'),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Failed to send order email to client: ' . $e->getMessage(), [
            'order_id' => $order->id,
            'email' => $clientEmail,
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => st('order.email.sent_error', 'Помилка відправки email'),
        ], 500);
    }
}

/**
 * Проверяет, рабочее ли сейчас время (на основе графика доставки)
 */
private function isWorkingHours(): bool
{
    $locations = Location::query()
        ->where('is_active', 1)
        ->orderBy('sort')
        ->get();

    if ($locations->isEmpty()) {
        // Если нет локаций, считаем рабочее время с 08:30 до 21:00
        $now = now();
        $currentTime = $now->format('H:i');
        return $currentTime >= '08:30' && $currentTime <= '21:00';
    }

    foreach ($locations as $location) {
        $schedule = $location->schedule ?? null;

        if (is_array($schedule) && !empty($schedule)) {
            foreach ($schedule as $scheduleItem) {
                $slug = trim($scheduleItem['slug'] ?? '');
                if ($slug !== 'delivery') {
                    continue;
                }

                $isActive = $scheduleItem['is_active'] ?? ($scheduleItem['data']['is_active'] ?? true);
                if ($isActive === false) {
                    continue;
                }

                $timeData = $scheduleItem['time'] ?? $scheduleItem['data']['time'] ?? null;
                $timeStr = null;

                if (is_array($timeData)) {
                    if (isset($timeData['uk']['uk']) && is_string($timeData['uk']['uk'])) {
                        $timeStr = $timeData['uk']['uk'];
                    } elseif (isset($timeData['uk']) && is_string($timeData['uk'])) {
                        $timeStr = $timeData['uk'];
                    }
                } elseif (is_string($timeData)) {
                    $timeStr = $timeData;
                }

                if ($timeStr) {
                    // Парсим время "з 09:00 до 21:00"
                    if (preg_match_all('/(\d{1,2}):(\d{2})/u', $timeStr, $timeMatches, PREG_SET_ORDER)) {
                        if (count($timeMatches) >= 2) {
                            $startTime = sprintf('%02d:%02d', (int)$timeMatches[0][1], (int)$timeMatches[0][2]);
                            $endTime = sprintf('%02d:%02d', (int)$timeMatches[1][1], (int)$timeMatches[1][2]);

                            $now = now();
                            $currentTime = $now->format('H:i');

                            return $currentTime >= $startTime && $currentTime <= $endTime;
                        }
                    }
                }
                break;
            }
        }
    }

    // Если не нашли график, считаем рабочее время с 08:30 до 21:00
    $now = now();
    $currentTime = $now->format('H:i');
    return $currentTime >= '08:30' && $currentTime <= '21:00';
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
