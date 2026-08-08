<?php

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaypartsBankTypeEnum;
use App\Models\Shop\Order;
use App\Models\Shop\PaypartsBank;
use App\Models\Shop\PaypartsRefund;
use App\Models\Shop\PaypartsTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PrivatBankPaypartsRefundService
{
    public function __construct(private readonly string $baseUrl) {}

    public static function make(): self
    {
        return new self(rtrim((string) config('services.payparts.privatbank.base_url'), '/'));
    }

    public function initiateFullRefund(Order $order, ?int $initiatedByUserId = null): PaypartsRefund
    {
        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if ($payment !== PaymentMethodEnum::PAYPARTS
            || ! in_array($order->payparts_status, ['payment_success', 'refund_failed'], true)) {
            throw new RuntimeException('Возврат доступен только для успешно оплаченного заказа «Оплатой частями».');
        }

        $transaction = $order->paypartsTransactions()
            ->where('status', 'payment_success')
            ->latest('id')
            ->first();

        if (! $transaction) {
            throw new RuntimeException('Успешная транзакция «Оплаты частями» не найдена.');
        }

        $refund = DB::transaction(function () use ($order, $transaction, $initiatedByUserId): PaypartsRefund {
            $lockedTransaction = PaypartsTransaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $blockingRefund = PaypartsRefund::query()
                ->where('payparts_transaction_id', $lockedTransaction->id)
                ->whereIn('status', ['refund_pending', 'refunded'])
                ->latest('id')
                ->first();

            if ($blockingRefund?->status === 'refunded') {
                throw new RuntimeException('Платёж уже полностью возвращён.');
            }

            if ($blockingRefund) {
                throw new RuntimeException('Возврат уже выполняется. Дождитесь проверки статуса.');
            }

            $amount = round((float) ($lockedTransaction->amount ?: $order->grand_total), 2);
            if ($amount <= 0) {
                throw new RuntimeException('Некорректная сумма возврата.');
            }

            return PaypartsRefund::create([
                'shop_order_id' => $order->id,
                'payparts_transaction_id' => $lockedTransaction->id,
                'payparts_bank_id' => $lockedTransaction->payparts_bank_id,
                'initiated_by_user_id' => $initiatedByUserId,
                'status' => 'refund_pending',
                'amount' => $amount,
                'order_id' => $lockedTransaction->order_id,
            ]);
        });

        $refund->loadMissing('transaction.bank');
        if ($this->isMonobank($refund->transaction?->bank)) {
            if ($refund->transaction?->token) {
                $refund->update(['order_id' => (string) $refund->transaction->token]);
            }

            return $this->initiateMonobankFullRefund($refund);
        }

        $declineAttempted = false;
        $declineDefinitivelyFailed = false;

        try {
            $refund = $this->sync($refund);
            if ($refund->status === 'refunded') {
                return $refund;
            }

            $transaction = $refund->transaction()->with('bank')->firstOrFail();
            $bank = $transaction->bank;
            if (! $bank) {
                throw new RuntimeException('Настройки банка для возврата не найдены.');
            }

            $remoteRefunded = $this->refundedAmount($refund->state_response_payload ?? []);
            $originalAmount = round((float) $transaction->amount, 2);
            $remainingAmount = round(max(0, $originalAmount - $remoteRefunded), 2);

            if ($remainingAmount <= 0.009) {
                return $this->complete($refund);
            }

            $payload = $this->declinePayload($bank, (string) $transaction->order_id, $remainingAmount);
            $refund->update([
                'amount' => $remainingAmount,
                'decline_request_payload' => $payload,
                'status' => 'refund_pending',
                'response_message' => null,
            ]);
            $this->setOrderRefundStatus($refund->order, 'refund_pending');

            $declineAttempted = true;
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(20)
                ->post($this->baseUrl . (string) config('services.payparts.privatbank.decline_path'), $payload);
            $responsePayload = $this->responsePayload($response);

            $refund->update(['decline_response_payload' => $responsePayload]);
            if ($response->successful()
                && $this->verifyResponseSignature($bank, $responsePayload, false)
                && strtoupper((string) ($responsePayload['state'] ?? '')) === 'FAIL') {
                $declineDefinitivelyFailed = true;
            }
            $this->assertSuccessfulResponse($response, $bank, $responsePayload, false);

            return $this->sync($refund->fresh());
        } catch (Throwable $e) {
            $status = $declineAttempted && ! $declineDefinitivelyFailed
                ? 'refund_pending'
                : 'refund_failed';
            $refund->update([
                'status' => $status,
                'response_message' => $e->getMessage(),
                'checked_at' => now(),
            ]);
            $this->setOrderRefundStatus($refund->order, $status);

            if ($status === 'refund_pending') {
                return $refund->fresh();
            }

            throw $e;
        }
    }

    public function sync(PaypartsRefund $refund): PaypartsRefund
    {
        if ($refund->status === 'refunded') {
            return $refund;
        }

        $refund->loadMissing(['transaction.bank', 'order']);
        $transaction = $refund->transaction;
        $bank = $transaction?->bank;

        if (! $transaction || ! $bank) {
            throw new RuntimeException('Транзакция или настройки банка для проверки возврата не найдены.');
        }

        if ($this->isMonobank($bank)) {
            return $this->syncMonobankRefund($refund);
        }

        $payload = $this->statePayload($bank, (string) $transaction->order_id);
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 500, throw: false)
            ->post($this->baseUrl . (string) config('services.payparts.privatbank.state_path'), $payload);
        $responsePayload = $this->responsePayload($response);

        $refund->update([
            'state_request_payload' => $payload,
            'state_response_payload' => $responsePayload,
            'checked_at' => now(),
        ]);

        $this->assertSuccessfulResponse($response, $bank, $responsePayload, true);

        $refundedAmount = $this->refundedAmount($responsePayload);
        if ($refundedAmount + 0.009 >= (float) $transaction->amount) {
            return $this->complete($refund);
        }

        $paymentState = strtoupper((string) ($responsePayload['paymentState'] ?? ''));
        if ($paymentState !== 'SUCCESS') {
            throw new RuntimeException('Платёж не находится в успешном состоянии: ' . ($paymentState ?: 'UNKNOWN') . '.');
        }

        $refund->update(['status' => 'refund_pending']);
        $this->setOrderRefundStatus($refund->order, 'refund_pending');

        return $refund->fresh();
    }

    private function initiateMonobankFullRefund(PaypartsRefund $refund): PaypartsRefund
    {
        $refund->loadMissing(['transaction.bank', 'order']);
        $transaction = $refund->transaction;

        if (! $transaction || ! $transaction->bank) {
            throw new RuntimeException('Monobank transaction or bank settings for refund were not found.');
        }

        try {
            $storeReturnId = 'refund_' . $refund->id . '_' . now()->format('YmdHis');
            $result = MonoBankPaypartsService::make()->returnPayment(
                $transaction,
                $storeReturnId,
                round((float) $refund->amount, 2),
                true
            );

            $refund->update([
                'decline_request_payload' => $result['request_payload'],
                'decline_response_payload' => $result['response_payload'],
                'response_message' => null,
                'checked_at' => now(),
            ]);

            try {
                $state = MonoBankPaypartsService::make()->fetchPaymentState($transaction);
                $refund->update([
                    'state_request_payload' => $state['request_payload'],
                    'state_response_payload' => $state['response_payload'],
                    'checked_at' => now(),
                ]);
            } catch (Throwable) {
                // Return request succeeded; state sync can be retried from the refunds page.
            }

            return $this->complete($refund->fresh());
        } catch (Throwable $e) {
            $refund->update([
                'status' => 'refund_failed',
                'response_message' => $e->getMessage(),
                'checked_at' => now(),
            ]);
            $this->setOrderRefundStatus($refund->order, 'refund_failed');

            throw $e;
        }
    }

    private function syncMonobankRefund(PaypartsRefund $refund): PaypartsRefund
    {
        $transaction = $refund->transaction;
        if (! $transaction) {
            throw new RuntimeException('Monobank transaction for refund sync was not found.');
        }

        $state = MonoBankPaypartsService::make()->fetchPaymentState($transaction);
        $payload = $state['response_payload'];

        $refund->update([
            'state_request_payload' => $state['request_payload'],
            'state_response_payload' => $payload,
            'checked_at' => now(),
        ]);

        $remoteState = strtoupper((string) ($payload['state'] ?? ''));
        $remoteSubState = strtoupper((string) ($payload['order_sub_state'] ?? ''));

        if ($remoteState === 'SUCCESS' && $remoteSubState === 'RETURNED') {
            return $this->complete($refund);
        }

        if ($remoteState === 'FAIL') {
            $refund->update([
                'status' => 'refund_failed',
                'response_message' => trim($remoteState . '/' . $remoteSubState, '/'),
            ]);
            $this->setOrderRefundStatus($refund->order, 'refund_failed');

            return $refund->fresh();
        }

        $refund->update(['status' => 'refund_pending']);
        $this->setOrderRefundStatus($refund->order, 'refund_pending');

        return $refund->fresh();
    }

    private function isMonobank(?PaypartsBank $bank): bool
    {
        return $bank?->bankType() === PaypartsBankTypeEnum::MonoBank;
    }

    private function declinePayload(PaypartsBank $bank, string $orderId, float $amount): array
    {
        $storeId = (string) $bank->store_id;
        $password = (string) $bank->account_password;
        $signature = base64_encode(sha1(
            $password . $storeId . $orderId . $this->signatureNumber($amount) . $password,
            true
        ));

        return [
            'storeId' => $storeId,
            'orderId' => $orderId,
            'amount' => $amount,
            'signature' => $signature,
        ];
    }

    private function statePayload(PaypartsBank $bank, string $orderId): array
    {
        $storeId = (string) $bank->store_id;
        $password = (string) $bank->account_password;

        return [
            'storeId' => $storeId,
            'orderId' => $orderId,
            'showRefund' => true,
            'showAmount' => true,
            'signature' => base64_encode(sha1($password . $storeId . $orderId . $password, true)),
        ];
    }

    private function assertSuccessfulResponse(
        Response $response,
        PaypartsBank $bank,
        array $payload,
        bool $withPaymentState
    ): void {
        if (! $response->successful()) {
            throw new RuntimeException('ПриватБанк вернул HTTP ' . $response->status() . '.');
        }

        if (! $this->verifyResponseSignature($bank, $payload, $withPaymentState)) {
            throw new RuntimeException('Некорректная подпись ответа ПриватБанка.');
        }

        if (strtoupper((string) ($payload['state'] ?? '')) !== 'SUCCESS') {
            throw new RuntimeException((string) ($payload['message'] ?? 'ПриватБанк отклонил операцию.'));
        }
    }

    private function verifyResponseSignature(PaypartsBank $bank, array $payload, bool $withPaymentState): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        if ($signature === '') {
            return false;
        }

        $password = (string) $bank->account_password;
        $value = $password
            . (string) ($payload['state'] ?? '')
            . (string) ($payload['storeId'] ?? $payload['storeIdentifier'] ?? '')
            . (string) ($payload['orderId'] ?? '');

        if ($withPaymentState) {
            $value .= (string) ($payload['paymentState'] ?? '');
        }

        $value .= (string) ($payload['message'] ?? '') . $password;
        $expected = base64_encode(sha1($value, true));

        return hash_equals($expected, $signature);
    }

    private function refundedAmount(array $payload): float
    {
        return round(collect($payload['refunds'] ?? [])->sum(
            fn (array $refund): float => (float) ($refund['Amount'] ?? $refund['amount'] ?? 0)
        ), 2);
    }

    private function complete(PaypartsRefund $refund): PaypartsRefund
    {
        $refund->update([
            'status' => 'refunded',
            'response_message' => null,
            'checked_at' => now(),
            'completed_at' => now(),
        ]);
        $this->setOrderRefundStatus($refund->order, 'refunded');

        return $refund->fresh();
    }

    private function setOrderRefundStatus(?Order $order, string $status): void
    {
        if ($order) {
            $order->forceFill(['payparts_status' => $status])->save();
        }
    }

    private function responsePayload(Response $response): array
    {
        return is_array($response->json()) ? $response->json() : [];
    }

    private function signatureNumber(float|int $value): string
    {
        return preg_replace('/\D+/', '', number_format((float) $value, 2, '.', ''));
    }
}
