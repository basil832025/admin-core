<?php

namespace App\Http\Controllers\Front;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Http\Controllers\Controller;
use App\Mail\OrderClientMail;
use App\Mail\OrderNotificationMail;
use App\Mail\CashalotReceiptMail;
use App\Models\Shop\Order;
use App\Models\Shop\PaypartsBank;
use App\Models\Shop\PaypartsTransaction;
use App\Services\CartService;
use App\Services\PrivatBankPaypartsService;
use App\Services\CashalotFiscalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaypartsController extends Controller
{
    public function response(Request $request)
    {
        Log::info('Payparts response received', [
            'method' => $request->method(),
            'full_url' => $request->fullUrl(),
            'query' => $request->query(),
            'payload' => $request->all(),
            'raw_body' => $request->getContent(),
            'headers' => [
                'content-type' => $request->header('content-type'),
                'user-agent' => $request->header('user-agent'),
                'x-forwarded-for' => $request->header('x-forwarded-for'),
                'x-real-ip' => $request->header('x-real-ip'),
                'host' => $request->header('host'),
            ],
        ]);

        $data = $request->input('data');
        $signature = (string) $request->input('signature', '');

        if ($data && $signature) {
            $payload = json_decode(base64_decode($data), true) ?: [];
        } else {
            $payload = $request->all();
            $signature = (string) ($payload['signature'] ?? '');
        }

        if ($payload === [] || $signature === '') {
            Log::warning('Payparts response empty payload', [
                'method' => $request->method(),
                'full_url' => $request->fullUrl(),
                'query' => $request->query(),
                'payload' => $request->all(),
                'raw_body' => $request->getContent(),
            ]);
            return response('error', 400);
        }

        $orderIdRaw = (string) ($payload['orderId'] ?? '');
        $token = (string) ($payload['token'] ?? '');
        $shopOrderId = $this->extractShopOrderId($orderIdRaw);
        $transaction = $this->findTransaction($orderIdRaw, $token);
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
                'method' => $request->method(),
                'full_url' => $request->fullUrl(),
                'query' => $request->query(),
                'payload' => $payload,
                'raw_body' => $request->getContent(),
            ]);
            return response('error', 400);
        }

        $state = strtolower((string) ($decoded['paymentState'] ?? $decoded['state'] ?? $decoded['status'] ?? 'payment_failed'));
        $internalStatus = $this->normalizeStatus($state);

        $transaction?->update([
            'status' => $internalStatus,
            'response_payload' => $decoded,
            'response_message' => $decoded['message'] ?? $transaction?->response_message,
        ]);

        $order = $shopOrderId ? Order::find($shopOrderId) : $transaction?->order;

        if ($order) {
            $this->applyOrderPaypartsStatus($order, $transaction, $internalStatus);

            if ($internalStatus === 'payment_success') {
                $this->sendSuccessNotifications($order, $transaction);
                $this->fiscalizePaidOrder($order, $transaction);
            }
        }

        return response('ok');
    }

    public function redirect(Request $request)
    {
        Log::info('Payparts redirect received', [
            'payload' => $request->all(),
        ]);

        $orderIdRaw = (string) $request->input('orderId', $request->query('orderId', ''));
        $token = (string) $request->input('token', $request->query('token', ''));
        $shopOrderId = $this->extractShopOrderId($orderIdRaw);
        $transaction = $this->findTransaction($orderIdRaw, $token);
        $order = $shopOrderId ? Order::find($shopOrderId) : $transaction?->order;

        // A browser redirect is not proof of payment. Only the signed server
        // callback handled by response() may persist payment_success.
        $state = strtolower((string) ($order?->payparts_status ?? ''));

        if ($order && $this->isFailedStatus($state)) {
            return $this->redirectToPaypartsOrder($order);
        }

        if ($order && $this->isSuccessStatus($state)) {
            $this->clearCheckoutSession($order);

            return $this->redirectToSuccess($order);
        }

        if ($order) {
            return $this->redirectToPaypartsOrder($order);
        }

        return redirect()->route('checkout')->with('error', 'Payparts payment was not completed.');
    }

    private function extractShopOrderId(string $orderId): int
    {
        if (preg_match('/^order_(\d+)/', $orderId, $matches)) {
            return (int) $matches[1];
        }

        return (int) preg_replace('/\D+/', '', $orderId);
    }

    private function findTransaction(string $orderId, string $token): ?PaypartsTransaction
    {
        if ($orderId !== '') {
            return PaypartsTransaction::where('order_id', $orderId)->latest('id')->first();
        }

        if ($token !== '') {
            return PaypartsTransaction::where('token', $token)->latest('id')->first();
        }

        return null;
    }

    private function normalizeStatus(string $state): string
    {
        if ($this->isSuccessStatus($state)) {
            return 'payment_success';
        }

        if ($this->isFailedStatus($state)) {
            return 'payment_failed';
        }

        if (in_array($state, ['payment_redirected', 'redirected'], true)) {
            return 'payment_redirected';
        }

        return 'pending_payment';
    }

    private function isSuccessStatus(string $state): bool
    {
        return in_array(strtolower($state), ['payment_success', 'success', 'sandbox', 'paid', 'locked'], true);
    }

    private function isFailedStatus(string $state): bool
    {
        return in_array(strtolower($state), ['payment_failed', 'failure', 'failed', 'declined', 'decline', 'error'], true);
    }

    private function applyOrderPaypartsStatus(Order $order, ?PaypartsTransaction $transaction, string $status): void
    {
        $isSuccess = $status === 'payment_success';

        if ($transaction && $transaction->status !== $status) {
            $transaction->update(['status' => $status]);
        }

        $order->status = $isSuccess ? OrderStatus::New : OrderStatus::Cart;
        $order->payment = PaymentMethodEnum::PAYPARTS;

        if ($order->isFillable('payparts_status')) {
            $order->payparts_status = $status;
        }

        if ($order->isFillable('paid_at') && $isSuccess && empty($order->paid_at)) {
            $order->paid_at = now();
        }

        $order->save();
    }

    private function sendSuccessNotifications(Order $order, ?PaypartsTransaction $transaction): void
    {
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

            $adminMailKey = 'payparts_admin_mail_sent:' . $order->id;
            if (! empty($notificationEmails) && Cache::add($adminMailKey, true, now()->addDays(30))) {
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
            Log::error('Payparts: failed to send order notification email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function fiscalizePaidOrder(Order $order, ?PaypartsTransaction $transaction): void
    {
        try {
            $cashalotLog = app(CashalotFiscalService::class)->fiscalizePaidOrder($order, [
                'payment_id' => (string) ($transaction?->order_id ?? $transaction?->token ?? ''),
                'paytype' => 'payparts',
                'status' => (string) ($transaction?->status ?? 'success'),
            ]);
            if (! $cashalotLog || $cashalotLog->status !== 'success') {
                return;
            }
            $order->loadMissing('clients');
            $clientEmail = trim((string) ($order->clients?->email ?? ''));
            if ($clientEmail === '' || ! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $mailKey = 'cashalot_receipt_mail_sent:' . $cashalotLog->id;
            if (Cache::add($mailKey, true, now()->addDays(30))) {
                Mail::to($clientEmail)->send(new CashalotReceiptMail($order, $cashalotLog));
            }
        } catch (\Throwable $e) {
            Log::error('Payparts callback: Cashalot fiscalization failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function clearCheckoutSession(Order $order): void
    {
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
    }

    private function redirectToSuccess(Order $order)
    {
        $locale = app()->getLocale();
        $routeName = in_array($locale, ['ru', 'en'], true) ? 'localized.checkout.success' : 'checkout.success';
        $routeParams = in_array($locale, ['ru', 'en'], true)
            ? ['locale' => $locale, 'order' => $order]
            : ['order' => $order];

        return redirect()->route($routeName, $routeParams);
    }

    private function redirectToPaypartsOrder(Order $order)
    {
        $locale = app()->getLocale();
        $routeName = in_array($locale, ['ru', 'en'], true) ? 'localized.checkout.pay.payparts' : 'checkout.pay.payparts';
        $routeParams = in_array($locale, ['ru', 'en'], true)
            ? ['locale' => $locale, 'order' => $order]
            : ['order' => $order];

        return redirect()->route($routeName, $routeParams);
    }
}
