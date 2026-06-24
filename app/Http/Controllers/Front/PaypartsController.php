<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Mail\OrderClientMail;
use App\Mail\OrderNotificationMail;
use App\Models\Shop\Order;
use App\Models\Shop\PaypartsBank;
use App\Models\Shop\PaypartsTransaction;
use App\Services\CartService;
use App\Services\PrivatBankPaypartsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaypartsController extends Controller
{
    public function response(Request $request)
    {
        $data = $request->input('data');
        $signature = (string) $request->input('signature', '');

        if ($data && $signature) {
            $payload = json_decode(base64_decode($data), true) ?: [];
        } else {
            $payload = $request->all();
            $signature = (string) ($payload['signature'] ?? '');
        }

        if ($payload === [] || $signature === '') {
            Log::warning('Payparts response empty payload', ['payload' => $request->all()]);
            return response('error', 400);
        }

        $orderIdRaw = (string) ($payload['orderId'] ?? '');
        $shopOrderId = (int) preg_replace('/\D+/', '', $orderIdRaw);

        $transaction = PaypartsTransaction::where('order_id', $orderIdRaw)->latest('id')->first();
        $bank = $transaction?->bank ?? PaypartsBank::find($transaction?->payparts_bank_id);

        if (! $bank) {
            Log::warning('Payparts response without bank', ['payload' => $payload]);
            return response('error', 404);
        }

        try {
            $decoded = $data
                ? PrivatBankPaypartsService::make()->decodeCallback($bank, $data, $signature)
                : $payload;

            if (! $data && ! PrivatBankPaypartsService::make()->verifyPaymentCallback($bank, $decoded)) {
                throw new \RuntimeException('Invalid PrivatBank payparts callback signature');
            }
        } catch (\Throwable $e) {
            Log::warning('Payparts response invalid signature', [
                'order_id' => $orderIdRaw,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return response('error', 400);
        }

        $state = strtolower((string) ($decoded['paymentState'] ?? $decoded['status'] ?? 'payment_failed'));
        $internalStatus = in_array($state, ['payment_success', 'success', 'sandbox', 'paid', 'locked'], true)
            ? 'payment_success'
            : (in_array($state, ['payment_failed', 'failure', 'declined', 'decline', 'error'], true)
                ? 'payment_failed'
                : (in_array($state, ['payment_redirected', 'redirected'], true) ? 'payment_redirected' : 'pending_payment'));

        $transaction?->update([
            'status' => $internalStatus,
            'response_payload' => $decoded,
            'response_message' => $decoded['message'] ?? $transaction?->response_message,
        ]);

        $order = $shopOrderId ? Order::find($shopOrderId) : null;

        if ($order) {
            $isSuccess = $internalStatus === 'payment_success';

            $order->status = $isSuccess ? OrderStatus::New : OrderStatus::Cart;
            $order->payment = PaymentMethodEnum::PAYPARTS;
            if ($order->isFillable('payparts_status')) {
                $order->payparts_status = $internalStatus;
            }
            if ($order->isFillable('paid_at') && $isSuccess && empty($order->paid_at)) {
                $order->paid_at = now();
            }
            $order->save();

            if ($isSuccess) {
                try {
                    $order->load([
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
                        $notificationEmails = ['info@3piroga.ua'];
                    }

                    if (! empty($notificationEmails)) {
                        Mail::to($notificationEmails)
                            ->locale('ru')
                            ->send(new OrderNotificationMail($order));
                    }

                    $clientEmail = trim((string) ($order->clients?->email ?? ''));
                    $mailLocale = in_array((string) ($transaction?->customer_locale ?? app()->getLocale()), ['uk', 'ru', 'en'], true)
                        ? (string) ($transaction?->customer_locale ?? app()->getLocale())
                        : app()->getLocale();

                    if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                        $mailKey = 'payparts_client_mail_sent:' . $order->id;

                        if (Cache::add($mailKey, true, now()->addDays(30))) {
                            Mail::to($clientEmail)
                                ->locale($mailLocale)
                                ->send(new OrderClientMail($order, $mailLocale));
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Payparts response: failed to send order notification email', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return response('ok');
    }

    public function redirect(Request $request)
    {
        $orderIdRaw = (string) $request->input('orderId', $request->query('orderId', ''));
        $shopOrderId = (int) preg_replace('/\D+/', '', $orderIdRaw);
        $transaction = $orderIdRaw !== ''
            ? PaypartsTransaction::where('order_id', $orderIdRaw)->latest('id')->first()
            : PaypartsTransaction::latest('id')->first();

        $state = strtolower((string) ($request->input('paymentState', $request->query('paymentState', '')) ?: ($transaction?->status ?? '')));
        $order = $shopOrderId ? Order::find($shopOrderId) : $transaction?->order;

        if ($order && in_array($state, ['payment_success', 'success', 'paid'], true)) {
            try {
                $cart = app(CartService::class);
                if (method_exists($cart, 'clearAfterCheckout')) {
                    $cart->clearAfterCheckout();
                }
                session()->forget('checkout.selected_promo');
                session()->forget('checkout.promo_discount');
                session()->forget('checkout.cart_signature');
                session()->forget('checkout.form_data');
            } catch (\Throwable $e) {
                Log::warning('Payparts redirect: failed to clear checkout session', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $locale = app()->getLocale();
            $routeName = in_array($locale, ['ru', 'en'], true) ? 'localized.checkout.success' : 'checkout.success';
            $routeParams = in_array($locale, ['ru', 'en'], true)
                ? ['locale' => $locale, 'order' => $order]
                : ['order' => $order];

            return redirect()->route($routeName, $routeParams);
        }

        return redirect()->route('checkout')->with('error', 'Оплата частинами не була завершена.');
    }
}
