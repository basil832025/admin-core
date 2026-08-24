<?php
// app/Http/Controllers/Auth/ClientAuthController.php
namespace App\Http\Controllers\Auth;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Mail\OrderClientMail;
use App\Mail\OrderNotificationMail;
use App\Models\Shop\Client;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\ClientRecipient;
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Services\LiqPayService;
use App\Services\Sms\EsputnikSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;



class ClientAuthController extends Controller
{
    protected function guard() { return Auth::guard('web'); }

    /**
     * Показать страницу авторизации (телефон + SMS)
     */
    public function show(Request $request)
    {

        // Если есть параметр redirect_to_checkout, сохраняем URL checkout в сессию
        if ($request->has('redirect_to_checkout')) {
            $locale = app()->getLocale();
            $checkoutUrl = Route::has('checkout')
                ? (in_array($locale, ['ru', 'en'], true) && Route::has('localized.checkout')
                    ? route('localized.checkout', ['locale' => $locale])
                    : route('checkout'))
                : route('cart.page');
            $request->session()->put('auth.redirect_to_checkout', $checkoutUrl);
        }

        $client = $this->guard()->user();
        $cartInfo = app(\App\Services\CartService::class)->info();
        $clientFirstName = $this->firstName((string) ($client?->name ?? ''));
        $clientSurname = trim((string) ($client?->surname ?? ''));
        if ($client) {
            $this->restoreCheckoutDeliveryFromDraft($request, $client);
        }
        $checkoutStep = $client !== null
            ? (string) $request->session()->get('checkout.step', ($clientFirstName !== '' && $clientSurname !== '' && trim((string) ($client?->email ?? '')) !== '' ? 'delivery' : 'recipient'))
            : 'phone';

        return view(front_view('auth.phone-sms'), [
            'isAuthenticated' => $client !== null,
            'authenticatedPhone' => $client?->phone,
            'authenticatedFirstName' => $clientFirstName,
            'authenticatedSurname' => $clientSurname,
            'authenticatedFirstNameVocative' => $this->vocativeName($clientFirstName),
            'checkoutStep' => $checkoutStep,
            'checkoutItems' => $cartInfo['items'] ?? [],
            'checkoutTotal' => (float) ($cartInfo['total'] ?? $cartInfo['total_price'] ?? 0),
        ]);
    }

    private function firstName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return '';
        }

        return (string) Str::of($name)->before(' ')->trim();
    }

    private function vocativeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '';
        }

        $last = mb_substr($name, -1);
        $base = mb_substr($name, 0, max(0, mb_strlen($name) - 1));

        return match (mb_strtolower($last)) {
            'а' => $base . 'о',
            'я' => $base . 'е',
            default => $name,
        };
    }

    public function checkout(Request $request)
    {
        if (! $this->guard()->check()) {
            return redirect()->route('auth.phone');
        }

        return $this->show($request);
    }

    private function restoreCheckoutDeliveryFromDraft(Request $request, Client $client): void
    {
        $draft = $this->findCheckoutDraftOrder(
            $client,
            (int) $request->session()->get('checkout.draft_order_id', 0),
            ['clientAddress', 'clientRecipient']
        );

        if (! $draft) {
            return;
        }

        $draftDelivery = $this->checkoutDeliveryFromDraftOrder($draft);
        if ($draftDelivery === []) {
            return;
        }

        $sessionDelivery = (array) $request->session()->get('checkout.delivery', []);
        $delivery = $draftDelivery;

        foreach ($sessionDelivery as $key => $value) {
            if (is_string($value) && trim($value) === '' && array_key_exists($key, $draftDelivery)) {
                continue;
            }

            if ($value === null && array_key_exists($key, $draftDelivery)) {
                continue;
            }

            $delivery[$key] = $value;
        }

        $request->session()->put('checkout.delivery', $delivery);
        $request->session()->put('checkout.draft_order_id', $draft->id);
    }

    private function checkoutDeliveryFromDraftOrder(Order $order): array
    {
        $deliveryMethod = $order->self_pickup
            ? 'sevia_pickup'
            : match ((string) ($order->nova_delivery_type ?? 'warehouse')) {
                'postomat' => 'nova_postomat',
                'courier' => 'nova_courier',
                default => 'nova_branch',
            };

        $address = $order->clientAddress;
        $recipient = $order->clientRecipient;
        $payment = ($order->payment instanceof PaymentMethodEnum ? $order->payment : PaymentMethodEnum::tryFrom((int) $order->payment)) === PaymentMethodEnum::CASH
            ? 'cash'
            : 'liqpay';

        return [
            'delivery_method' => $deliveryMethod,
            'city' => (string) ($order->nova_city ?: ($address?->city ?? '')),
            'city_ref' => (string) ($order->nova_city_ref ?? ''),
            'city_name' => (string) ($order->nova_city ?? ''),
            'city_display_name' => (string) ($order->nova_city ?? ''),
            'city_details' => (string) ($order->nova_city_details ?? ''),
            'warehouse_ref' => (string) ($order->nova_warehouse_ref ?? ''),
            'warehouse_name' => (string) ($order->nova_warehouse ?? ''),
            'street_ref' => (string) ($address?->street_place_id ?? ''),
            'street' => (string) ($address?->street ?? ''),
            'house' => (string) ($address?->house ?? ''),
            'apartment' => (string) ($address?->apartment ?? ''),
            'floor' => (string) ($address?->floor ?? ''),
            'entrance' => (string) ($address?->entrance ?? ''),
            'elevator' => (string) ($address?->elevator ?? ''),
            'bring_to_floor' => (bool) ($address?->bring_to_floor ?? false),
            'payment' => $payment,
            'shipping_price' => (float) ($order->shipping_price ?? 0),
            'comment' => (string) ($order->notes ?? ''),
            'other_recipient' => $recipient !== null,
            'other_recipient_surname' => (string) ($recipient?->surname ?? ''),
            'other_recipient_name' => (string) ($recipient?->name ?? ''),
            'other_recipient_patronymic' => (string) ($recipient?->patronymic ?? ''),
            'other_recipient_phone' => (string) ($recipient?->phone ?? ''),
            'confirm_without_call' => (bool) ($order->confirm_without_call ?? false),
            'gift_no_receipt' => (bool) ($order->gift_no_receipt ?? false),
        ];
    }

    private function findCheckoutDraftOrder(Client $client, int $preferredId = 0, array $with = []): ?Order
    {
        if ($preferredId > 0) {
            $preferred = Order::query()
                ->with($with)
                ->whereKey($preferredId)
                ->where('clients_id', $client->id)
                ->where('status', OrderStatus::Cart->value)
                ->first();

            if ($preferred) {
                return $preferred;
            }
        }

        return Order::query()
            ->with($with)
            ->withCount('items')
            ->where('clients_id', $client->id)
            ->where('status', OrderStatus::Cart->value)
            ->orderByDesc('items_count')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function saveCheckoutRecipient(Request $request)
    {
        $client = $this->guard()->user();

        if (! $client) {
            return response()->json([
                'ok' => false,
                'message' => 'Потрібно підтвердити телефон.',
            ], 401);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:150'],
        ], [
            'first_name.required' => 'Вкажіть імʼя.',
            'last_name.required' => 'Вкажіть прізвище.',
            'email.required' => 'Вкажіть електронну пошту.',
            'email.email' => 'Введіть коректну адресу електронної пошти.',
        ]);

        $client->name = trim((string) $data['first_name']);
        $client->surname = trim((string) $data['last_name']);
        $client->email = trim((string) $data['email']);
        if (is_null($client->phone_verified_at)) {
            $client->phone_verified_at = now();
        }
        $client->save();

        $request->session()->put('checkout.step', 'delivery');
        $request->session()->put('checkout.recipient', [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim((string) $data['first_name']),
            'surname' => trim((string) $data['last_name']),
            'full_name' => trim($data['first_name'] . ' ' . $data['last_name']),
            'email' => $client->email,
            'phone' => $client->phone,
        ]);

        return response()->json([
            'ok' => true,
            'redirect' => route('checkout'),
        ]);
    }

    public function saveCheckoutDelivery(Request $request)
    {
        $client = $this->guard()->user();

        if (! $client) {
            return response()->json([
                'ok' => false,
                'message' => 'Потрібно підтвердити телефон.',
            ], 401);
        }

        $data = $request->validate([
            'delivery_method' => ['nullable', Rule::in(['nova_branch', 'nova_postomat', 'nova_courier', 'sevia_pickup'])],
            'city' => ['nullable', 'string', 'max:255'],
            'city_ref' => ['nullable', 'string', 'max:80'],
            'city_name' => ['nullable', 'string', 'max:255'],
            'city_display_name' => ['nullable', 'string', 'max:255'],
            'city_details' => ['nullable', 'string', 'max:255'],
            'warehouse_ref' => ['nullable', 'string', 'max:80'],
            'warehouse_name' => ['nullable', 'string', 'max:255'],
            'street_ref' => ['nullable', 'string', 'max:80'],
            'street' => ['nullable', 'string', 'max:255'],
            'house' => ['nullable', 'string', 'max:40'],
            'apartment' => ['nullable', 'string', 'max:40'],
            'floor' => ['nullable', 'string', 'max:20'],
            'entrance' => ['nullable', 'string', 'max:40'],
            'elevator' => ['nullable', Rule::in(['', 'unknown', 'yes', 'no'])],
            'bring_to_floor' => ['nullable', 'boolean'],
            'payment' => ['nullable', Rule::in(['liqpay', 'cash'])],
            'shipping_price' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'other_recipient' => ['nullable', 'boolean'],
            'other_recipient_surname' => ['nullable', 'string', 'max:80'],
            'other_recipient_name' => ['nullable', 'string', 'max:80'],
            'other_recipient_patronymic' => ['nullable', 'string', 'max:80'],
            'other_recipient_phone' => ['nullable', 'string', 'max:32'],
            'confirm_without_call' => ['nullable', 'boolean'],
            'gift_no_receipt' => ['nullable', 'boolean'],
        ]);

        $deliveryMethod = (string) ($data['delivery_method'] ?? 'nova_branch');

        if ($deliveryMethod !== 'sevia_pickup' && (string) ($data['payment'] ?? 'liqpay') === 'cash') {
            $data['payment'] = 'liqpay';
        }

        $delivery = [
            'delivery_method' => $deliveryMethod,
            'city' => trim((string) ($data['city'] ?? '')),
            'city_ref' => trim((string) ($data['city_ref'] ?? '')),
            'city_name' => trim((string) ($data['city_name'] ?? '')),
            'city_display_name' => trim((string) ($data['city_display_name'] ?? '')),
            'city_details' => trim((string) ($data['city_details'] ?? '')),
            'warehouse_ref' => trim((string) ($data['warehouse_ref'] ?? '')),
            'warehouse_name' => trim((string) ($data['warehouse_name'] ?? '')),
            'street_ref' => trim((string) ($data['street_ref'] ?? '')),
            'street' => trim((string) ($data['street'] ?? '')),
            'house' => trim((string) ($data['house'] ?? '')),
            'apartment' => trim((string) ($data['apartment'] ?? '')),
            'floor' => trim((string) ($data['floor'] ?? '')),
            'entrance' => trim((string) ($data['entrance'] ?? '')),
            'elevator' => (string) ($data['elevator'] ?? ''),
            'bring_to_floor' => (bool) ($data['bring_to_floor'] ?? false),
            'payment' => (string) ($data['payment'] ?? 'liqpay'),
            'shipping_price' => (float) ($data['shipping_price'] ?? 0),
            'comment' => trim((string) ($data['comment'] ?? '')),
            'other_recipient' => (bool) ($data['other_recipient'] ?? false),
            'other_recipient_surname' => trim((string) ($data['other_recipient_surname'] ?? '')),
            'other_recipient_name' => trim((string) ($data['other_recipient_name'] ?? '')),
            'other_recipient_patronymic' => trim((string) ($data['other_recipient_patronymic'] ?? '')),
            'other_recipient_phone' => trim((string) ($data['other_recipient_phone'] ?? '')),
            'confirm_without_call' => (bool) ($data['confirm_without_call'] ?? false),
            'gift_no_receipt' => (bool) ($data['gift_no_receipt'] ?? false),
        ];

        $request->session()->put('checkout.delivery', $delivery);
        $draft = $this->saveCheckoutDraftOrder($client, $delivery);

        return response()->json([
            'ok' => true,
            'draft_order_id' => $draft?->id,
        ]);
    }

    public function submitCheckout(Request $request)
    {
        $client = $this->guard()->user();

        if (! $client) {
            return response()->json([
                'ok' => false,
                'message' => 'Потрібно підтвердити телефон.',
            ], 401);
        }

        $delivery = (array) $request->session()->get('checkout.delivery', []);
        $this->validateCheckoutDelivery($delivery);

        $order = $this->saveCheckoutDraftOrder($client, $delivery);

        if (! $order || $order->items()->count() === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Кошик порожній.',
            ], 422);
        }

        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if ($payment === PaymentMethodEnum::LIQPAY) {
            return response()->json([
                'ok' => true,
                'redirect' => route('checkout.pay.liqpay', $order),
            ]);
        }

        $wasAlreadyNew = $order->status === OrderStatus::New;
        $order->status = OrderStatus::New;
        $order->save();

        if (! $wasAlreadyNew) {
            $this->sendAdminOrderNotification($order);
            $this->sendClientOrderConfirmation($order);
        }

        $request->session()->forget(['checkout.delivery', 'checkout.recipient', 'checkout.step', 'checkout.draft_order_id']);

        return response()->json([
            'ok' => true,
            'redirect' => route('checkout.success', $order),
        ]);
    }

    public function payLiqPay(Order $order)
    {
        $this->authorizeCheckoutOrder($order);

        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if ($payment !== PaymentMethodEnum::LIQPAY) {
            return redirect()->route('checkout.success', $order);
        }

        $order->load(['clients', 'items.product.parent']);
        $clientEmail = trim((string) ($order->clients?->email ?? ''));
        $emailRequired = $clientEmail === '' || ! filter_var($clientEmail, FILTER_VALIDATE_EMAIL);
        $liqpayForm = $emailRequired ? '' : LiqPayService::make()->formForOrder($order, 'uk');

        return view('front.sevia::checkout.liqpay', [
            'order' => $order,
            'clientEmail' => $clientEmail,
            'emailRequired' => $emailRequired,
            'liqpayForm' => $liqpayForm,
        ]);
    }

    public function saveLiqPayEmail(Request $request, Order $order)
    {
        $this->authorizeCheckoutOrder($order);

        $data = $request->validate([
            'contact_email' => ['required', 'string', 'email:rfc', 'max:150'],
        ], [
            'contact_email.required' => 'Вкажіть електронну пошту.',
            'contact_email.email' => 'Введіть коректну адресу електронної пошти.',
        ]);

        $client = $order->clients ?: $this->guard()->user();
        if ($client) {
            $client->email = trim((string) $data['contact_email']);
            $client->save();
        }

        return redirect()
            ->route('checkout.pay.liqpay', $order)
            ->with('success', 'Email збережено. Тепер можна перейти до оплати.');
    }

    public function checkoutSuccess(Order $order)
    {
        $this->syncSuccessfulLiqPayPayment($order);

        $this->authorizeCheckoutSuccess($order->refresh());

        session()->forget(['checkout.delivery', 'checkout.recipient', 'checkout.step', 'checkout.draft_order_id']);

        $order->refresh()->load(['clients', 'clientAddress', 'clientRecipient', 'items.product.parent']);

        return view('front.sevia::checkout.success', [
            'order' => $order,
        ]);
    }

    public function sendOrderToEmail(Request $request, Order $order)
    {
        $this->authorizeCheckoutOrder($order);

        $sent = $this->sendClientOrderConfirmation($order, true);

        return response()->json([
            'ok' => $sent,
            'message' => $sent ? 'Замовлення надіслано на email.' : 'У клієнта не вказаний коректний email.',
        ], $sent ? 200 : 422);
    }

    private function validateCheckoutDelivery(array $delivery): void
    {
        $method = (string) ($delivery['delivery_method'] ?? 'nova_branch');

        if (! in_array($method, ['nova_branch', 'nova_postomat', 'nova_courier', 'sevia_pickup'], true)) {
            throw ValidationException::withMessages(['delivery_method' => 'Оберіть спосіб доставки.']);
        }

        if ($method !== 'sevia_pickup') {
            if (trim((string) ($delivery['city_ref'] ?? '')) === '') {
                throw ValidationException::withMessages(['city' => 'Оберіть місто доставки.']);
            }

            if (in_array($method, ['nova_branch', 'nova_postomat'], true)
                && trim((string) ($delivery['warehouse_ref'] ?? '')) === '') {
                throw ValidationException::withMessages(['warehouse_ref' => 'Оберіть відділення або поштомат.']);
            }

            if ($method === 'nova_courier'
                && (trim((string) ($delivery['street'] ?? '')) === '' || trim((string) ($delivery['house'] ?? '')) === '')) {
                throw ValidationException::withMessages(['street' => 'Вкажіть вулицю та будинок.']);
            }
        }

        if ((bool) ($delivery['other_recipient'] ?? false)) {
            foreach (['other_recipient_surname', 'other_recipient_name', 'other_recipient_phone'] as $field) {
                if (trim((string) ($delivery[$field] ?? '')) === '') {
                    throw ValidationException::withMessages([$field => 'Заповніть дані іншого отримувача.']);
                }
            }
        }
    }

    private function authorizeCheckoutOrder(Order $order): void
    {
        $client = $this->guard()->user();

        abort_unless($client && (int) $order->clients_id === (int) $client->id, 404);
    }

    private function authorizeCheckoutSuccess(Order $order): void
    {
        $client = $this->guard()->user();

        if ($client && (int) $order->clients_id === (int) $client->id) {
            return;
        }

        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        abort_unless($payment === PaymentMethodEnum::LIQPAY && $order->status !== OrderStatus::Cart, 404);
    }

    private function sendAdminOrderNotification(Order $order): bool
    {
        try {
            $order->loadMissing([
                'items.product.parent.productCharacteristicValues.characteristic.svgImage',
                'items.product.productCharacteristicValues.characteristic.svgImage',
                'items.product.productCharacteristicValues.characteristicValue',
                'adjustments',
                'clientAddress',
                'clients',
            ]);

            $notificationEmails = config('notifications.order_notification_email', []);
            if (is_string($notificationEmails)) {
                $notificationEmails = array_filter(array_map('trim', explode(',', $notificationEmails)));
            }

            if (empty($notificationEmails)) {
                $notificationEmails = array_filter([config('mail.from.address')]);
            }

            if (empty($notificationEmails)) {
                return false;
            }

            Mail::to($notificationEmails)->locale('uk')->send(new OrderNotificationMail($order));

            return true;
        } catch (\Throwable $e) {
            Log::error('Checkout: failed to send order notification email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendClientOrderConfirmation(Order $order, bool $force = false): bool
    {
        try {
            $order->loadMissing(['clients']);
            $clientEmail = trim((string) ($order->clients?->email ?? ''));

            if ($clientEmail === '' || ! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            $mailKey = 'order_client_mail_sent:' . $order->id;
            if (! $force && ! Cache::add($mailKey, true, now()->addDays(30))) {
                return true;
            }

            Mail::to($clientEmail)->locale('uk')->send(new OrderClientMail($order, 'uk'));

            return true;
        } catch (\Throwable $e) {
            Log::error('Checkout: failed to send client order email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function syncSuccessfulLiqPayPayment(Order $order): void
    {
        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if ($payment !== PaymentMethodEnum::LIQPAY || $order->status !== OrderStatus::Cart) {
            return;
        }

        try {
            $statusPayload = LiqPayService::make()->statusForOrder($order);
            $status = (string) ($statusPayload['status'] ?? '');

            Log::info('Checkout success: LiqPay status fallback', [
                'order_id' => $order->id,
                'status' => $status,
                'payload' => $statusPayload,
            ]);

            if (! in_array($status, ['success', 'sandbox'], true)) {
                return;
            }

            $order->status = OrderStatus::New;
            $order->payment = PaymentMethodEnum::LIQPAY;
            if (empty($order->paid_at)) {
                $order->paid_at = now();
            }
            $order->save();

            $this->sendAdminOrderNotification($order);
            $this->sendClientOrderConfirmation($order);
        } catch (\Throwable $e) {
            Log::error('Checkout success: failed to sync LiqPay status', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveCheckoutDraftOrder(Client $client, array $delivery): ?Order
    {
        $order = $this->findCheckoutDraftOrder($client, (int) session('checkout.draft_order_id', 0));

        if (! $order) {
            $order = new Order();
            $order->clients_id = $client->id;
            $order->status = OrderStatus::Cart;
            $order->currency = 'UAH';
            $order->total_price = 0;
            $order->subtotal = 0;
            $order->total_price_sale = 0;
        }

        $deliveryMethod = (string) ($delivery['delivery_method'] ?? 'nova_branch');
        $isPickup = $deliveryMethod === 'sevia_pickup';
        $payment = (string) ($delivery['payment'] ?? 'liqpay');
        $shippingPrice = max(0, (float) ($delivery['shipping_price'] ?? 0));

        $cityName = trim((string) ($delivery['city_display_name'] ?? $delivery['city_name'] ?? $delivery['city'] ?? ''));
        $cityDetails = trim((string) ($delivery['city_details'] ?? ''));
        $warehouseName = trim((string) ($delivery['warehouse_name'] ?? ''));
        $street = trim((string) ($delivery['street'] ?? ''));
        $house = trim((string) ($delivery['house'] ?? ''));
        $apartment = trim((string) ($delivery['apartment'] ?? ''));
        $floor = trim((string) ($delivery['floor'] ?? ''));
        $entrance = trim((string) ($delivery['entrance'] ?? ''));
        $streetRef = trim((string) ($delivery['street_ref'] ?? ''));
        $isCourier = $deliveryMethod === 'nova_courier';
        $otherRecipientSurname = trim((string) ($delivery['other_recipient_surname'] ?? ''));
        $otherRecipientName = trim((string) ($delivery['other_recipient_name'] ?? ''));
        $otherRecipientPatronymic = trim((string) ($delivery['other_recipient_patronymic'] ?? ''));
        $otherRecipientPhone = trim((string) ($delivery['other_recipient_phone'] ?? ''));
        $hasOtherRecipientData = $otherRecipientSurname !== '' && $otherRecipientName !== '' && $otherRecipientPhone !== '';

        $order->shipping_method = $isPickup ? 'self_pickup' : 'nova_post';
        $order->self_pickup = $isPickup;
        $order->shipping_price = $isPickup ? 0 : $shippingPrice;
        $order->shipping_total = $isPickup ? 0 : $shippingPrice;
        $order->nova_delivery_type = $isPickup ? null : match ($deliveryMethod) {
            'nova_postomat' => 'postomat',
            'nova_courier' => 'courier',
            default => 'warehouse',
        };
        $order->nova_city = $isPickup ? '' : $cityName;
        $order->nova_city_details = $isPickup ? '' : $cityDetails;
        $order->nova_city_ref = $isPickup ? '' : (string) ($delivery['city_ref'] ?? '');
        $order->nova_warehouse = $isPickup || $isCourier ? '' : $warehouseName;
        $order->nova_warehouse_ref = $isPickup || $isCourier ? '' : (string) ($delivery['warehouse_ref'] ?? '');
        $order->client_address_id = $isCourier && $street !== '' && $house !== ''
            ? $this->saveCheckoutCourierAddress($client, $delivery, $cityName, $cityDetails, $street, $house, $apartment, $floor, $entrance, $streetRef)?->id
            : null;
        $order->payment = $payment === 'cash' && $isPickup
            ? PaymentMethodEnum::CASH
            : PaymentMethodEnum::LIQPAY;
        $order->notes = (string) ($delivery['comment'] ?? '');
        $order->confirm_without_call = (bool) ($delivery['confirm_without_call'] ?? false);
        $order->gift_no_receipt = (bool) ($delivery['gift_no_receipt'] ?? false);

        if ((bool) ($delivery['other_recipient'] ?? false) && $hasOtherRecipientData) {
            $recipient = ClientRecipient::query()->firstOrCreate([
                'client_id' => $client->id,
                'phone' => $otherRecipientPhone,
            ], [
                'surname' => $otherRecipientSurname,
                'name' => $otherRecipientName,
                'patronymic' => $otherRecipientPatronymic,
            ]);

            $recipient->fill([
                'surname' => $otherRecipientSurname,
                'name' => $otherRecipientName,
                'patronymic' => $otherRecipientPatronymic,
            ])->save();

            $order->client_recipient_id = $recipient->id;
            $order->recipient_surname = $recipient->surname;
            $order->recipient_name = $recipient->name;
            $order->recipient_patronymic = $recipient->patronymic;
            $order->recipient_phone = $recipient->phone;
        } else {
            $order->client_recipient_id = null;
            $order->recipient_surname = (string) ($client->surname ?? '');
            $order->recipient_name = (string) ($client->name ?? '');
            $order->recipient_patronymic = null;
            $order->recipient_phone = (string) ($client->phone ?? '');
        }

        if (! $order->exists) {
            $order->save();
        }

        $this->syncCheckoutBottleOrderItems($order);

        $itemsSubtotal = (float) $order->items()->sum(\Illuminate\Support\Facades\DB::raw('qty * unit_price'));
        $order->subtotal = $itemsSubtotal;
        $order->total_price = $itemsSubtotal;
        $order->total_price_sale = max(0, $itemsSubtotal - (float) ($order->discount_total ?? 0));
        $order->grand_total = max(0, round((float) $order->total_price_sale, 2));

        $order->save();
        session()->put('checkout.draft_order_id', $order->id);

        return $order;
    }

    private function syncCheckoutBottleOrderItems(Order $order): void
    {
        $order->loadMissing(['items']);

        $items = $order->items;
        $items
            ->filter(fn (OrderItem $item): bool => data_get($item->meta, 'line_type') === 'bottle')
            ->each
            ->delete();

        $items
            ->filter(fn (OrderItem $item): bool => data_get($item->meta, 'line_type') !== 'bottle')
            ->each(function (OrderItem $item) use ($order): void {
                $meta = is_array($item->meta) ? $item->meta : [];
                $bottleProductId = (int) ($meta['bottle_id'] ?? 0);
                $bottlePrice = (float) ($meta['bottle_price'] ?? 0);

                if ($bottleProductId <= 0 || $bottlePrice <= 0) {
                    return;
                }

                $bottleItem = new OrderItem();
                $bottleItem->shop_order_id = $order->id;
                $bottleItem->product_id = $bottleProductId;
                $bottleItem->qty = max(1, (int) $item->qty);
                $bottleItem->unit_price = $bottlePrice;
                $bottleItem->currency = $item->currency ?: 'UAH';
                $bottleItem->meta = [
                    'line_type' => 'bottle',
                    'parent_order_item_id' => $item->id,
                    'parent_product_id' => $item->product_id,
                    'parent_volume' => (string) ($meta['volume'] ?? ''),
                    'bottle_title' => (string) ($meta['bottle_title'] ?? ''),
                    'bottle_description' => (string) ($meta['bottle_description'] ?? ''),
                    'bottle_image' => (string) ($meta['bottle_image'] ?? ''),
                ];
                $bottleItem->save();
            });

        $order->unsetRelation('items');
    }

    private function saveCheckoutCourierAddress(
        Client $client,
        array $delivery,
        string $cityName,
        string $cityDetails,
        string $street,
        string $house,
        string $apartment,
        string $floor,
        string $entrance,
        string $streetRef,
    ): ?ClientAddress {
        $address = ClientAddress::query()
            ->where('client_id', $client->id)
            ->when($streetRef !== '', fn ($query) => $query->where('street_place_id', $streetRef))
            ->when($streetRef === '', fn ($query) => $query->where('street', $street))
            ->where('house', $house)
            ->where('apartment', $apartment)
            ->first();

        if (! $address) {
            $address = new ClientAddress();
            $address->client_id = $client->id;
        }

        $city = trim($cityName . ($cityDetails !== '' ? ', ' . $cityDetails : ''));

        $address->fill([
            'city' => $city,
            'street' => $street,
            'house' => $house,
            'apartment' => $apartment,
            'floor' => $floor,
            'entrance' => $entrance,
            'bring_to_floor' => (bool) ($delivery['bring_to_floor'] ?? false),
            'elevator' => ($delivery['elevator'] ?? '') !== '' ? (string) $delivery['elevator'] : null,
            'street_place_id' => $streetRef,
            'formatted_address' => trim(implode(', ', array_filter([
                $city,
                $street,
                $house,
                $apartment !== '' ? 'кв. ' . $apartment : '',
            ]))),
            'note' => null,
            'type' => 'home',
            'is_private_house' => $apartment === '',
        ]);
        $address->save();

        return $address;
    }

    /**
     * Получить URL для редиректа после авторизации
     * Если пользователь был на checkout - возвращаем туда, иначе - профиль
     */
    protected function getRedirectUrl(Request $request): string
    {
        $checkoutUrl = $request->session()->pull('auth.redirect_to_checkout');
        
        // Логируем для отладки
        \Log::info('getRedirectUrl called', [
            'checkoutUrl' => $checkoutUrl,
            'session_id' => $request->session()->getId(),
            'all_session_keys' => array_keys($request->session()->all()),
        ]);
        
        if ($checkoutUrl && str_contains($checkoutUrl, '/checkout')) {
            return $checkoutUrl;
        }

        $locale = app()->getLocale();
        if (in_array($locale, ['ru', 'en'], true) && Route::has('localized.profile.index')) {
            return route('localized.profile.index', ['locale' => $locale]);
        }

        if (Route::has('profile.index')) {
            return route('profile.index');
        }

        return Route::has('cart.page') ? route('cart.page') : url('/cart');
    }

    // === Socialite ===
    public function redirect(string $provider)
    {
        abort_unless($this->isSocialiteProviderConfigured($provider), 404);

        $scopes = $provider === 'facebook' ? ['email'] : [];
        return Socialite::driver($provider)->scopes($scopes)->redirect();
    }

    public function callback(string $provider, Request $request)
    {
        abort_unless($this->isSocialiteProviderConfigured($provider), 404);

        $social = Socialite::driver($provider)->user();

        $providerId = (string) $social->getId();
        $email      = $social->getEmail();
        $name       = $social->getName() ?: $social->getNickname();

        $client = Client::query()
            ->where('provider_name',$provider)
            ->where('provider_id',$providerId)
            ->first();

        if (!$client && $email) {
            $client = Client::where('email',$email)->first();
        }

        if (!$client) {
            $client = new Client();
            $client->email = $email; // може бути null
        }

        if (!$client->name) $client->name = $name ?: 'Клієнт';
        $client->provider_name = $provider;
        $client->provider_id   = $providerId;

        if ($client->email && !$client->email_verified_at) {
            $client->email_verified_at = now();
        }

        if (empty($client->password)) {
            $client->password = Hash::make(Str::random(24));
        }

        $client->save();

        $this->guard()->login($client, true);
        $request->session()->regenerate();

        $redirectUrl = $this->getRedirectUrl($request);
        return redirect($redirectUrl);
    }

    private function isSocialiteProviderConfigured(string $provider): bool
    {
        if (! in_array($provider, ['google', 'facebook'], true)) {
            return false;
        }

        $config = (array) config("services.{$provider}", []);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }

    // === Реєстрація з підтвердженням телефону ===
    public function register(Request $r, EsputnikSms $sms)
    {
        // 1) нормализуем
        $digits = $this->normalizePhone((string)$r->input('phone'));
        $r->merge(['phone' => $digits]);

        // 2) валидация
        $data = $r->validate([
            'name'     => ['required','string','max:255'],
            'phone'    => ['required','regex:/^380\d{9}$/'],
            'email'    => ['nullable','email','max:255'],
            'password' => ['required','string','min:6','max:100','confirmed'],
        ]);

        // 3) проверка дубля (учесть старые форматы)
        $candidates = array_unique([
            $digits,                       // 380XXXXXXXXX
            '0'.substr($digits,3),         // 0XXXXXXXXX
            substr($digits,3),             // XXXXXXXXX
        ]);

        /** @var \App\Models\Shop\Client|null $existing */
        $existing = \App\Models\Shop\Client::whereIn('phone', $candidates)->first();

        if ($existing) {
            // если уже верифицирован — это полноценный дубль
            if ($existing->phone_verified_at) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Цей номер вже зареєстрований. Увійдіть у кабінет.',
                    'errors' => ['phone' => ['phone_already_taken']],
                ], 422);
            }

            // не верифицирован: разрешаем отправку коду як завершення реєстрації
            // можно подставить name/email/password из запроса в payload,
            // но НЕ создавать новую запись сейчас.
        }

        // 4) кладём payload у cache на TTL і надсилаємо SMS
        $ttl         = (int) env('ESPUTNIK_SMS_TTL', 180);
        $resendAfter = (int) env('ESPUTNIK_SMS_RESEND_AFTER', 60);
        $key         = 'reg:'.$digits;

        if (Cache::has($key.':resend_lock')) {
            throw ValidationException::withMessages(['phone' => 'Повторна відправка поки недоступна.']);
        }

        $code = random_int(1000,9999);

        Cache::put($key, [
            'code'    => (string)$code,
            'payload' => [
                'name'     => $data['name'],
                'phone'    => $digits,                 // нормализованный!
                'email'    => $data['email'] ?? null,
                'password' => bcrypt($r->input('password')),
            ],
            'attempts' => 0,
        ], $ttl);

        Cache::put($key.':resend_lock', 1, $resendAfter);

        $resp = $sms->sendCode($digits, (string)$code, null, [
            'message_type' => 'register',
            'client_id' => $existing?->id,
            'raw_phone' => (string) $r->input('phone'),
        ]);
        if (($resp['status'] ?? 500) >= 300) {
            Cache::forget($key);
            Cache::forget($key.':resend_lock');
            return response()->json([
                'ok' => false,
                'message' => 'Не вдалося відправити SMS. Перевірте відправника та баланс.',
            ], 422);
        }

        return response()->json(['ok'=>true, 'ttl'=>$ttl, 'resend_in'=>$resendAfter]);
    }


    public function sendSms(Request $r)
    {
        $r->validate(['phone'=>'required']);
        session([
            'client_verify_code'  => '1234',
            'client_verify_phone' => $r->string('phone'),
        ]);
        return response()->json(['ok'=>true]);
    }

    public function verifySms(Request $r)
    {
        $digits = $this->normalizePhone((string)$r->input('phone'));
        $r->merge(['phone' => $digits]);

        $data = $r->validate([
            'phone' => ['required','regex:/^380\d{9}$/'],
            'code'  => ['required','digits:4'],
        ]);

        $key   = 'reg:'.$data['phone'];
        $state = Cache::get($key);

        if (!$state) {
            return response()->json([
                'message' => 'Код прострочений. Запросіть новий.',
                'errors'  => ['code' => ['expired']],
            ], 422);
        }

        if ((string)$state['code'] !== (string)$data['code']) {
            return response()->json([
                'message' => st('auth.code_invalid', 'Невірний код. Перевірте цифри та спробуйте ще раз.'),
                'errors'  => ['code' => ['invalid']],
            ], 422);
        }

        $p = $state['payload'];


        $client = Client::firstOrCreate(
            ['phone' => $data['phone']],                        // нормализованный
            [
                'name'     => $p['name'] ?? 'Клієнт',
                'email'    => $p['email'] ?? null,
                'password' => $p['password'],                   // уже bcrypt
            ]
        );

        if (is_null($client->phone_verified_at)) {
            $client->phone_verified_at = now();
            $client->save();
        }

        $this->guard()->login($client, true);

        Cache::forget($key);
        Cache::forget($key.':resend_lock');

        return response()->json([
            'ok' => true,
            'redirect' => $this->getRedirectUrl($r),
        ]);
    }


    private function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);   // тільки цифри
        if (Str::startsWith($d, '0'))  $d = '38'.$d; // 0XXXXXXXXX -> 380XXXXXXXXX
        if (Str::startsWith($d, '3800')) $d = '380' . substr($d, 4);
        if (strlen($d) === 9)          $d = '380'.$d;
        if (Str::startsWith($d, '380') === false && strlen($d) >= 10) {
            // залишаємо останні 9 цифр + 380 (на випадок вставок з +38)
            $d = '380'.substr($d, -9);
        }
        return $d;
    }

    public function login(Request $r)
    {
        // 1) Нормализация ввода -> 380XXXXXXXXX
        $raw    = (string) $r->input('phone');
        $digits = preg_replace('/\D+/', '', $raw);     // "+38 0XX ..." -> "380XXXXXXXXX"
        if (str_starts_with($digits, '0'))  $digits = '38' . $digits; // 0XXXXXXXXX -> 380XXXXXXXXX
        if (strlen($digits) === 9)          $digits = '380' . $digits;

        // для validate() подставляем уже нормализованное
        $r->merge(['phone' => $digits]);

        // 2) Валидация с понятными сообщениями
        $r->validate(
            [
                'phone'    => ['required', 'regex:/^380\d{9}$/'],
                'password' => ['required', 'string'],
            ],
            [
                'phone.required'   => 'Вкажіть номер телефону',
                'phone.regex'      => 'Невірний формат номера телефону. Приклад: +38 0XX XXX XX XX',
                'password.required'=> 'Вкажіть пароль',
            ]
        );

        // 3) Поиск пользователя по всем возможным форматам в БД
        //    приоритет: точное совпадение 380XXXXXXXXX, затем 0XXXXXXXXX, затем XXXXXXXXX
        $candidates = array_unique([
            $digits,                                // 380XXXXXXXXX
            '0' . substr($digits, 3),               // 0XXXXXXXXX
            substr($digits, 3),                     // XXXXXXXXX
        ]);

        $user = \App\Models\Shop\Client::query()
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $v) {
                    $q->orWhere('phone', $v);
                }
            })
            // упорядочим так, чтобы сначала шел правильный формат
            ->when(function () { return true; }, function ($q) use ($candidates) {
                $placeholders = implode(',', array_fill(0, count($candidates), '?'));
                $q->orderByRaw("FIELD(phone, $placeholders)", $candidates);
            })
            ->first();

        // 4) Проверка пароля и единое сообщение об ошибке
        if (!$user || !\Hash::check($r->input('password'), $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'phone' => 'Невірний логін або пароль',
            ]);
        }

        // 5) Мягкая миграция: если в БД был старый формат — приводим к 380XXXXXXXXX
        if ($user->phone !== $digits) {
            try {
                $user->phone = $digits;
                $user->save();
            } catch (\Throwable $e) {
                // если внезапно конфликт уникальности — просто пропустим миграцию
            }
        }

        // 6) Логин
        $this->guard()->login($user, true);
        $r->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => $this->getRedirectUrl($r),
        ]);
    }



    // === Авторизация только по телефону + SMS (без пароля) ===
    public function loginPhoneSms(Request $r, \App\Services\Sms\EsputnikSms $sms)
    {
        // 1) Нормализация ввода -> 380XXXXXXXXX
        $raw    = (string) $r->input('phone');
        $digits = $this->normalizePhone($raw);
        $r->merge(['phone' => $digits]);

        // 2) Валидация
        $r->validate(
            [
                'phone' => ['required', 'regex:/^380\d{9}$/'],
            ],
            [
                'phone.required' => 'Вкажіть номер телефону',
                'phone.regex'    => 'Невірний формат номера телефону. Приклад: +38 0XX XXX XX XX',
            ]
        );

        // 3) Проверяем, существует ли пользователь
        $candidates = array_unique([
            $digits,
            '0' . substr($digits, 3),
            substr($digits, 3),
        ]);

        $client = Client::query()
            ->where(function ($q) use ($candidates) {
                foreach ($candidates as $v) {
                    $q->orWhere('phone', $v);
                }
            })
            ->first();

        // Если пользователя нет, создаем его автоматически (без имени, только номер телефона)
        if (!$client) {
            $client = new Client();
            $client->phone = $digits;
            // Имя оставляем null - пользователь сможет заполнить его в профиле позже
            $client->password = Hash::make(Str::random(24)); // Генерируем случайный пароль
            $client->save();
        }

        // 4) Отправка SMS кода
        $ttl         = (int) env('ESPUTNIK_SMS_TTL', 180);
        $resendAfter = (int) env('ESPUTNIK_SMS_RESEND_AFTER', 60);
        $key         = 'login_sms:' . $digits;

        if (Cache::has($key . ':resend_lock')) {
            return response()->json([
                'ok'      => false,
                'message' => 'Повторна відправка поки недоступна. Спробуйте через ' . $resendAfter . ' секунд.',
            ], 422);
        }

        // Генерируем одноразовый код и сохраняем состояние
        $code = (string) random_int(1000, 9999);

        Cache::put($key, [
            'code'      => $code,
            'client_id' => $client->id,
            'attempts'  => 0,
        ], $ttl);

        Cache::put($key . ':resend_lock', 1, $resendAfter);

        // Реальная отправка SMS через API
        $resp = $sms->sendCode($digits, $code, null, [
            'message_type' => 'login',
            'client_id' => $client->id,
            'raw_phone' => (string) $r->input('phone'),
        ]);
        if (($resp['status'] ?? 500) >= 300) {
            Log::error('SMS отправка не удалась', [
                'sms_log_id' => $resp['log_id'] ?? null,
                'phone' => $digits,
                'status' => $resp['status'] ?? 'unknown',
            ]);
            
            Cache::forget($key);
            Cache::forget($key . ':resend_lock');
            return response()->json([
                'ok'      => false,
                'message' => 'Не вдалося відправити SMS. Перевірте відправника та баланс.',
            ], 422);
        }
        
        return response()->json([
            'ok'        => true,
            'ttl'       => $ttl,
            'resend_in' => $resendAfter,
        ]);
    }

    public function verifyPhoneSms(Request $r)
    {
        $digits = $this->normalizePhone((string) $r->input('phone'));
        $r->merge(['phone' => $digits]);

        $data = $r->validate([
            'phone' => ['required', 'regex:/^380\d{9}$/'],
            'code'  => ['required', 'digits:4'],
        ], [
            'phone.required' => 'Вкажіть номер телефону',
            'phone.regex'    => 'Невірний формат номера телефону',
            'code.required'  => 'Введіть 4 цифри',
            'code.digits'    => 'Введіть 4 цифри',
        ]);

        $key   = 'login_sms:' . $data['phone'];
        $state = Cache::get($key);

        if (!$state) {
            return response()->json([
                'ok'      => false,
                'message' => 'Код прострочений. Запросіть новий.',
                'errors'  => ['code' => ['expired']],
            ], 422);
        }

        $attempts = (int) ($state['attempts'] ?? 0);
        if ($attempts >= 5) {
            Cache::forget($key);
            return response()->json([
                'ok'      => false,
                'message' => 'Забагато спроб. Спробуйте пізніше або надішліть код ще раз.',
                'errors'  => ['code' => ['too_many_attempts']],
            ], 422);
        }

        // Проверяем код из кэша
        if ((string) $state['code'] !== (string) $data['code']) {
            $state['attempts'] = $attempts + 1;
            Cache::put($key, $state, now()->addMinutes(3));
            return response()->json([
                'ok'      => false,
                'message' => st('auth.code_invalid', 'Невірний код. Перевірте цифри та спробуйте ще раз.'),
                'errors'  => ['code' => ['invalid']],
            ], 422);
        }

        // Находим клиента
        $client = Client::find($state['client_id']);
        if (!$client) {
            Cache::forget($key);
            return response()->json([
                'ok'      => false,
                'message' => 'Клієнт не знайдений.',
            ], 422);
        }

        // Обновляем телефон до нормализованного формата, если нужно
        if ($client->phone !== $digits) {
            try {
                $client->phone = $digits;
                $client->save();
            } catch (\Throwable $e) {
                // Игнорируем ошибку, если номер уже используется
            }
        }

        // Проверяем верификацию телефона
        if (is_null($client->phone_verified_at)) {
            $client->phone_verified_at = now();
            $client->save();
        }

        // Логин с remember на 30 дней
        $this->guard()->login($client, true);
        $r->session()->regenerate();

        Cache::forget($key);
        Cache::forget($key . ':resend_lock');

        return response()->json([
            'ok'       => true,
            'redirect' => $this->getRedirectUrl($r),
        ]);
    }

    /**
     * Сохранить URL checkout для редиректа после авторизации
     */
    public function saveCheckoutUrl(Request $request)
    {
        $url = $request->input('url');
        
        // Логируем для отладки
        \Log::info('saveCheckoutUrl called', [
            'url' => $url,
            'session_id' => $request->session()->getId(),
        ]);
        
        if ($url && str_contains($url, '/checkout')) {
            $request->session()->put('auth.redirect_to_checkout', $url);
            
            // Логируем после сохранения
            \Log::info('saveCheckoutUrl saved', [
                'saved_url' => $request->session()->get('auth.redirect_to_checkout'),
                'session_id' => $request->session()->getId(),
            ]);
        }
        return response()->json(['ok' => true]);
    }

    public function logout(Request $r)
    {
        $this->guard()->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return back();
    }
}
