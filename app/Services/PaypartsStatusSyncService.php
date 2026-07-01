<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Mail\CashalotReceiptMail;
use App\Models\Shop\Order;
use App\Models\Shop\PaypartsTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaypartsStatusSyncService
{
    /**
     * @return array{checked: bool, status: string, order_changed: bool, remote_state: string}
     */
    public function sync(PaypartsTransaction $transaction): array
    {
        $transaction->loadMissing(['bank', 'order']);
        $order = $transaction->order;

        if (! $order instanceof Order || ! $transaction->bank) {
            throw new \RuntimeException('Payparts transaction order or bank is missing');
        }

        $pendingStatuses = ['payment_redirected', 'pending_payment'];
        if (! in_array((string) $order->payparts_status, $pendingStatuses, true)) {
            return [
                'checked' => false,
                'status' => (string) $order->payparts_status,
                'order_changed' => false,
                'remote_state' => '',
            ];
        }

        $syncKey = 'payparts_status_sync:' . $transaction->id;
        if (! Cache::add($syncKey, now()->timestamp, 8)) {
            return [
                'checked' => false,
                'status' => (string) $order->payparts_status,
                'order_changed' => false,
                'remote_state' => '',
            ];
        }

        $sync = PrivatBankPaypartsService::make()->fetchPaymentState($transaction);
        $remote = (array) ($sync['response_payload'] ?? []);
        $remoteState = strtolower((string) ($remote['paymentState'] ?? $remote['state'] ?? $remote['status'] ?? ''));
        $internalStatus = $this->normalizeStatus($remoteState);

        $transaction->update([
            'status' => $internalStatus,
            'response_payload' => $remote,
            'response_message' => $remote['message'] ?? $transaction->response_message,
        ]);

        $orderChanged = false;
        if (in_array($internalStatus, ['payment_success', 'payment_failed'], true)) {
            $orderChanged = (string) $order->payparts_status !== $internalStatus;
            $order->forceFill([
                'payparts_status' => $internalStatus,
                'status' => $internalStatus === 'payment_success' ? OrderStatus::New : OrderStatus::Cart,
                'payment' => PaymentMethodEnum::PAYPARTS,
                'paid_at' => $internalStatus === 'payment_success' && empty($order->paid_at) ? now() : $order->paid_at,
            ])->save();

            if ($internalStatus === 'payment_success') {
                $this->fiscalizePaidOrder($order, $transaction);
            }
        }

        return [
            'checked' => true,
            'status' => $internalStatus,
            'order_changed' => $orderChanged,
            'remote_state' => $remoteState,
        ];
    }

    private function normalizeStatus(string $state): string
    {
        if (in_array($state, ['payment_success', 'success', 'sandbox', 'paid', 'locked'], true)) {
            return 'payment_success';
        }

        if (in_array($state, ['payment_failed', 'failure', 'failed', 'declined', 'decline', 'error'], true)) {
            return 'payment_failed';
        }

        if (in_array($state, ['payment_redirected', 'redirected'], true)) {
            return 'payment_redirected';
        }

        return 'pending_payment';
    }

    private function fiscalizePaidOrder(Order $order, PaypartsTransaction $transaction): void
    {
        try {
            $cashalotLog = app(CashalotFiscalService::class)->fiscalizePaidOrder($order, [
                'payment_id' => (string) ($transaction->order_id ?: $transaction->token ?: ''),
                'paytype' => 'payparts',
                'status' => (string) $transaction->status,
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
            Log::error('Payparts status sync: Cashalot fiscalization failed', [
                'order_id' => $order->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}