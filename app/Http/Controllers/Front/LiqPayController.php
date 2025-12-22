<?php
// app/Http/Controllers/Front/LiqPayController.php
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Shop\Order;
use App\Models\Shop\LiqPayLog;
use App\Services\LiqPayService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                $order->status  = OrderStatus::New;
                $order->payment = PaymentMethodEnum::LIQPAY;

                if ($order->isFillable('paid_at')) {
                    $order->paid_at = now();
                }

                $order->save();
            }
        }

        return 'ok';
    }
}
