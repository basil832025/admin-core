<?php

namespace App\Services;

use App\Models\Shop\CashalotLog;
use App\Models\Shop\Order;
use Illuminate\Support\Str;

class CashalotFiscalService
{
    public function fiscalizePaidOrder(Order $order, ?array $liqpayPayload = null): ?CashalotLog
    {
        if (! (bool) config('cashalot.enabled', false)) {
            return null;
        }

        if (! $this->hasRequiredConfig()) {
            return CashalotLog::create([
                'shop_order_id' => $order->id,
                'status' => 'skipped',
                'error_code' => 'CONFIG_MISSING',
                'error_message' => 'Cashalot config values are missing',
            ]);
        }

        $existingSuccess = CashalotLog::query()
            ->where('shop_order_id', $order->id)
            ->where('status', 'success')
            ->latest('id')
            ->first();

        if ($existingSuccess) {
            return $existingSuccess;
        }

        $order->loadMissing('items.product');

        $requestPayload = $this->buildRequestPayload($order, $liqpayPayload ?? []);
        $lastLiqpayLogId = $order->lastLiqpayLog?->id;

        $log = CashalotLog::create([
            'shop_order_id' => $order->id,
            'liqpay_log_id' => $lastLiqpayLogId,
            'status' => 'pending',
            'request_payload' => $requestPayload,
        ]);

        try {
            $response = app(CashalotApiClient::class)->registerCheck($requestPayload);

            $errorCode = (string) ($response['ErrorCode'] ?? '');
            $isSuccess = mb_strtolower($errorCode) === 'ok' || ! empty($response['NumFiscal']);

            $log->fill([
                'status' => $isSuccess ? 'success' : 'failed',
                'error_code' => $response['ErrorCode'] ?? null,
                'error_message' => $response['ErrorMessage'] ?? null,
                'num_fiscal' => isset($response['NumFiscal']) ? (string) $response['NumFiscal'] : null,
                'receipt_url' => isset($response['Url']) ? (string) $response['Url'] : null,
                'response_payload' => $response,
                'fiscalized_at' => $isSuccess ? now() : null,
            ])->save();

            if ($isSuccess) {
                $this->sendToConsumer(
                    $order,
                    $log,
                    $response,
                    (float) data_get($requestPayload, 'CHECKTOTAL.SUM', (float) ($order->grand_total ?? 0))
                );
            }
        } catch (\Throwable $e) {
            $log->fill([
                'status' => 'failed',
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ])->save();
        }

        return $log;
    }

    protected function sendToConsumer(Order $order, CashalotLog $log, array $registerResponse, float $orderTotal): void
    {
        if (! (bool) config('cashalot.send_to_consumer', true)) {
            $log->fill([
                'consumer_status' => 'skipped',
                'consumer_error_code' => 'DISABLED',
                'consumer_error_message' => 'Consumer sending is disabled by config',
            ])->save();

            return;
        }

        $order->loadMissing('clients');

        $phone = $this->normalizePhone($order->clients?->phone);
        $serviceType = (int) config('cashalot.consumer_service_type', 0);

        if ($phone === null) {
            $log->fill([
                'consumer_service_type' => $serviceType,
                'consumer_status' => 'skipped',
                'consumer_error_code' => 'PHONE_MISSING',
                'consumer_error_message' => 'Client phone is empty or invalid',
            ])->save();

            return;
        }

        $consumerResponse = app(CashalotApiClient::class)->sendCheckToConsumer([
            'service_type' => $serviceType,
            'phone_number' => $phone,
            'registrar_num_fiscal' => (string) config('cashalot.numfiscal', ''),
            'order_num_fiscal' => (string) ($registerResponse['NumFiscal'] ?? ''),
            'order_sum' => $orderTotal,
            'order_date_time' => (string) ($registerResponse['OrderDateTime'] ?? now()->format('Y-m-d\TH:i:s')),
        ]);

        $consumerOk = mb_strtolower((string) ($consumerResponse['ErrorCode'] ?? '')) === 'ok';

        $log->fill([
            'consumer_service_type' => $serviceType,
            'consumer_phone' => $phone,
            'consumer_status' => $consumerOk ? 'sent' : 'failed',
            'consumer_error_code' => $consumerResponse['ErrorCode'] ?? null,
            'consumer_error_message' => $consumerResponse['ErrorMessage'] ?? null,
            'consumer_response_payload' => $consumerResponse,
            'sent_to_consumer_at' => $consumerOk ? now() : null,
        ])->save();
    }

    protected function hasRequiredConfig(): bool
    {
        return trim((string) config('cashalot.numfiscal', '')) !== ''
            && trim((string) config('cashalot.certificate', '')) !== ''
            && trim((string) config('cashalot.key', '')) !== ''
            && trim((string) config('cashalot.password', '')) !== '';
    }

    protected function buildRequestPayload(Order $order, array $liqpayPayload): array
    {
        $total = (float) ($order->grand_total ?? $order->total_price ?? 0);
        $total = round(max(0, $total), 2);

        $items = [];
        foreach ($order->items as $item) {
            $qty = (float) ($item->qty ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $price = (float) ($item->unit_price ?? 0);
            $cost = round($qty * $price, 2);
            $name = trim((string) ($item->title ?? $item->product?->short_name ?? $item->product?->name ?? 'Товар'));

            $items[] = [
                'NAME' => Str::limit($name, 180, ''),
                'UNITCD' => 2009,
                'UNITNM' => 'шт',
                'AMOUNT' => round($qty, 3),
                'PRICE' => round($price, 2),
                'COST' => $cost,
            ];
        }

        if ($items === []) {
            $items[] = [
                'NAME' => 'Замовлення #' . $order->id,
                'UNITCD' => 2009,
                'UNITNM' => 'шт',
                'AMOUNT' => 1,
                'PRICE' => $total,
                'COST' => $total,
            ];
        }

        $shippingAmount = (float) ($order->shipping_price ?? 0);
        if ($shippingAmount <= 0) {
            $shippingAmount = (float) ($order->shipping_total ?? 0);
        }
        $shippingAmount = round(max(0, $shippingAmount), 2);

        if ($shippingAmount > 0) {
            $items[] = [
                'NAME' => 'Доставка',
                'UNITCD' => 2009,
                'UNITNM' => 'шт',
                'AMOUNT' => 1,
                'PRICE' => $shippingAmount,
                'COST' => $shippingAmount,
            ];
        }

        $itemsTotal = round((float) collect($items)->sum(fn (array $row): float => (float) ($row['COST'] ?? 0)), 2);
        $effectiveDiscount = round(max(0, $itemsTotal - $total), 2);
        $delta = round($total - ($itemsTotal - $effectiveDiscount), 2);

        if ($delta > 0) {
            $items[] = [
                'NAME' => 'Коригування',
                'UNITCD' => 2009,
                'UNITNM' => 'шт',
                'AMOUNT' => 1,
                'PRICE' => $delta,
                'COST' => $delta,
            ];

            $itemsTotal = round($itemsTotal + $delta, 2);
        } elseif ($delta < 0) {
            $effectiveDiscount = round($effectiveDiscount + abs($delta), 2);
        }

        $paytype = trim((string) ($liqpayPayload['paytype'] ?? ''));
        $paysysName = $paytype !== ''
            ? 'LiqPay (' . $paytype . ')'
            : 'LiqPay';
        $taxNum = trim((string) ($liqpayPayload['transaction_id'] ?? $liqpayPayload['payment_id'] ?? ''));
        if ($taxNum === '') {
            $taxNum = 'LIQPAY';
        }

        return [
            'CHECKHEAD' => [
                'DOCTYPE' => 'SaleGoods',
                'DOCSUBTYPE' => 'CheckGoods',
                'TESTING' => $this->isTestingPayment($liqpayPayload),
            ],
            'CHECKTOTAL' => [
                'SUM' => $total,
                'DISCOUNTSUM' => $effectiveDiscount,
            ],
            'CHECKPAY' => [
                [
                    'PAYFORMCD' => 1,
                    'PAYFORMNM' => 'Банківська картка',
                    'SUM' => $total,
                    'PAYSYS' => [
                        [
                            'TAXNUM' => $taxNum,
                            'NAME' => $paysysName,
                            'SUM' => $total,
                            'COMMISSION' => 0,
                        ],
                    ],
                ],
            ],
            'CHECKBODY' => $items,
        ];
    }

    protected function isTestingPayment(array $liqpayPayload): bool
    {
        $status = mb_strtolower(trim((string) ($liqpayPayload['status'] ?? '')));

        if ($status === 'sandbox') {
            return true;
        }

        return (bool) config('liqpay.sandbox', false);
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }

        if (strlen($digits) === 10) {
            $digits = '38' . $digits;
        }

        if (strlen($digits) < 12) {
            return null;
        }

        return '+' . $digits;
    }
}
