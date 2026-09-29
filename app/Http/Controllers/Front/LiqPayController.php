<?php
// app/Http/Controllers/Front/LiqPayController.php
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Shop\Order;
use App\Models\Shop\LiqPayLog;
use App\Services\LiqPayService;
use App\Services\CashalotFiscalService;
use App\Services\ScheduleV2Service;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Mail\CashalotReceiptMail;
use App\Mail\OrderClientMail;
use App\Mail\OrderNotificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LiqPayController extends Controller
{
    public function callback(Request $request)
    {
        // создаём сервис из конфига
        $liqpay = LiqPayService::make();

        // лог при заходе
        Log::info('LiqPay callback HIT', [
            'all' => $request->all(),
            'ip'  => $request->ip(),
        ]);

        $data      = $request->input('data');
        $signature = $request->input('signature');

        if (! $data || ! $signature) {
            Log::warning('LiqPay callback: empty payload');
            return 'error';
        }

        try {
            $payload = $liqpay->decodeCallback($data, $signature);
        } catch (\Throwable $e) {
            Log::warning('LiqPay callback: invalid signature', [
                'data' => $data,
                'err'  => $e->getMessage(),
            ]);

            return 'error';
        }

        $orderIdRaw   = $payload['order_id'] ?? null;          // "order_123"
        $shopOrderId  = $orderIdRaw ? (int) str_replace('order_', '', $orderIdRaw) : null;

        LiqPayLog::create([
            'log_date'            => now(),
            'signature'           => $signature,
            'payment_id'          => $payload['payment_id']      ?? null,
            'action'              => $payload['action']          ?? null,
            'status'              => $payload['status']          ?? null,
            'type'                => $payload['type']            ?? null,
            'paytype'             => $payload['paytype']         ?? null,
            'acq_id'              => $payload['acq_id']          ?? null,
            'shop_order_id'       => $shopOrderId,
            'order_id'            => $payload['order_id']        ?? null,
            'liqpay_order_id'     => $payload['liqpay_order_id'] ?? null,
            'description'         => $payload['description']     ?? null,
            'sender_phone'        => $payload['sender_phone']        ?? null,
            'sender_first_name'   => $payload['sender_first_name']   ?? null,
            'sender_last_name'    => $payload['sender_last_name']    ?? null,
            'sender_card_mask2'   => $payload['sender_card_mask2']   ?? null,
            'sender_card_bank'    => $payload['sender_card_bank']    ?? null,
            'sender_card_type'    => $payload['sender_card_type']    ?? null,
            'sender_card_country' => $payload['sender_card_country'] ?? null,
            'amount'              => $payload['amount']              ?? null,
            'currency'            => $payload['currency']            ?? null,
            'sender_commission'   => $payload['sender_commission']   ?? null,
            'receiver_commission' => $payload['receiver_commission'] ?? null,
            'amount_debit'        => $payload['amount_debit']        ?? null,
            'amount_credit'       => $payload['amount_credit']       ?? null,
            'commission_debit'    => $payload['commission_debit']    ?? null,
            'commission_credit'   => $payload['commission_credit']   ?? null,
            'language'            => $payload['language']           ?? null,
            'create_date'         => $payload['create_date']        ?? null,
            'end_date'            => $payload['end_date']           ?? null,
            'transaction_id'      => $payload['transaction_id']     ?? null,
            'payload'             => $payload,
        ]);

        $status = $payload['status'] ?? null;
        $isOk   = in_array($status, ['success', 'sandbox'], true);

        if ($shopOrderId && $isOk) {
            $order = Order::find($shopOrderId);

            if ($order) {
                // Чтобы не слать письма повторно при дублях callback'а.
                $wasAlreadyFinalized = ($order->status !== OrderStatus::Cart);

                // Оплата может быть подтверждена спустя несколько минут после выбора
                // слота. Не создаём новый заказ с уже прошедшим временем доставки.
                $deliveryTimeIsValid = $this->ensure3pirogaDeliveryTimeIsValid($order);

                $order->status = $deliveryTimeIsValid ? OrderStatus::New : OrderStatus::OnHold;
                $order->payment = PaymentMethodEnum::LIQPAY;

                if (! $deliveryTimeIsValid) {
                    $order->extra_reason = 'Оплату підтверджено після завершення обраного часу доставки. Потрібно узгодити новий час.';
                }

                if (empty($order->paid_at)) {
                    $order->paid_at = now();
                }

                $order->save();

                // Если заказ только что перешёл в статус "Новый" после успешной оплаты —
                // отправляем админам уведомление, как при обычном оформлении.
                if (! $wasAlreadyFinalized) {
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
                        if (is_string($notificationEmails)) {
                            $notificationEmails = array_filter(array_map('trim', explode(',', $notificationEmails)));
                        }
                        if (empty($notificationEmails)) {
                            $notificationEmails = ['info@3piroga.ua'];
                        }

                        if (!empty($notificationEmails)) {
                            Log::info('LiqPay callback: sending order notification email', [
                                'order_id' => $order->id,
                                'emails'   => $notificationEmails,
                            ]);
                            Mail::to($notificationEmails)
                                ->locale('uk')
                                ->send(new OrderNotificationMail($order));
                        } else {
                            Log::warning('LiqPay callback: order notification email not configured', [
                                'order_id' => $order->id,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::error('LiqPay callback: failed to send order notification email', [
                            'order_id' => $order->id,
                            'error'    => $e->getMessage(),
                            'trace'    => $e->getTraceAsString(),
                        ]);
                    }

                    try {
                        $order->loadMissing(['clients']);
                        $clientEmail = trim((string) ($order->clients?->email ?? ''));

                        if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                            $mailKey = 'order_client_mail_sent:' . $order->id;

                            if (Cache::add($mailKey, true, now()->addDays(30))) {
                                Mail::to($clientEmail)->locale('uk')->send(new OrderClientMail($order, 'uk'));
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::error('LiqPay callback: failed to send client order email', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                try {
                    $cashalotLog = app(CashalotFiscalService::class)->fiscalizePaidOrder($order, $payload);

                    if ($cashalotLog && $cashalotLog->status === 'success') {
                        $order->loadMissing('clients');

                        $clientEmail = trim((string) ($order->clients?->email ?? ''));

                        if ($clientEmail !== '' && filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                            $mailKey = 'cashalot_receipt_mail_sent:' . $cashalotLog->id;

                            if (Cache::add($mailKey, true, now()->addDays(30))) {
                                Mail::to($clientEmail)->send(new CashalotReceiptMail($order, $cashalotLog));
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('LiqPay callback: Cashalot fiscalization failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return 'ok';
    }

    /**
     * Moves an expired scheduled delivery to the first available 3piroga slot.
     *
     * A false result means that no safe replacement slot was found, so the
     * paid order must remain on hold for an operator instead of entering work
     * with a delivery time in the past.
     */
    private function ensure3pirogaDeliveryTimeIsValid(Order $order): bool
    {
        if (config('project.name') !== '3piroga' || $order->self_pickup || $order->as_soon_possible) {
            return true;
        }

        $date = trim((string) $order->getRawOriginal('date_order'));
        $time = trim((string) $order->getRawOriginal('time_order'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
            Log::warning('LiqPay callback: scheduled delivery has invalid date or time', [
                'order_id' => $order->id,
                'date_order' => $date,
                'time_order' => $time,
            ]);

            return false;
        }

        try {
            $deliveryAt = \Carbon\Carbon::createFromFormat('Y-m-d', $date, 'Europe/Kyiv')
                ->setTime((int) $matches[1], (int) $matches[2]);
        } catch (\Throwable) {
            return false;
        }

        $now = now('Europe/Kyiv');
        if ($deliveryAt->gt($now)) {
            return true;
        }

        $location = Location::query()
            ->where('is_active', 1)
            ->orderByDesc('schedule_v2_enabled')
            ->orderBy('sort')
            ->first();
        $schedule = app(ScheduleV2Service::class);

        if (! $location || ! $schedule->isEnabled($location)) {
            Log::warning('LiqPay callback: expired delivery slot has no schedule for rescheduling', [
                'order_id' => $order->id,
                'delivery_at' => $deliveryAt->toIso8601String(),
            ]);

            return false;
        }

        for ($offset = 0; $offset < 15; $offset++) {
            $candidateDate = $now->copy()->startOfDay()->addDays($offset);

            if (! $schedule->isDateAvailable($location, 'delivery', $candidateDate, $now)) {
                continue;
            }

            $slots = $schedule->buildSlotsForDate($location, 'delivery', $candidateDate, $now);
            if ($slots === []) {
                continue;
            }

            $newTime = explode('-', $slots[0])[0];
            $order->date_order = $candidateDate->toDateString();
            $order->time_order = $newTime;

            Log::warning('LiqPay callback: expired delivery slot was rescheduled', [
                'order_id' => $order->id,
                'old_delivery_at' => $deliveryAt->toIso8601String(),
                'new_delivery_at' => $candidateDate->toDateString().' '.$newTime,
            ]);

            return true;
        }

        Log::warning('LiqPay callback: no replacement slot found for expired delivery', [
            'order_id' => $order->id,
            'delivery_at' => $deliveryAt->toIso8601String(),
        ]);

        return false;
    }
}
