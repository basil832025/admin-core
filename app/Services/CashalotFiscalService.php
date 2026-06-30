<?php

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Models\Shop\CashalotLog;
use App\Models\Shop\Order;
use Illuminate\Support\Str;

class CashalotFiscalService
{
    protected function prroConfig(): array
    {
        return app(PrroConfigService::class)->getForCashalot();
    }

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
                'check_sum' => $this->resolveCheckSum($order),
                'payment_type' => $this->resolvePaymentTypeLabel($order),
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
            'check_sum' => (float) data_get($requestPayload, 'CHECKTOTAL.SUM', $this->resolveCheckSum($order)),
            'payment_type' => $this->resolvePaymentTypeLabel($order, $liqpayPayload ?? []),
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

    public function fiscalizeOfflinePaidOrder(Order $order): ?CashalotLog
    {
        return $this->fiscalizePaidOrder($order, []);
    }

    public function fiscalizeReturnCheck(Order $order, CashalotLog $sourceLog, ?int $userId = null): ?CashalotLog
    {
        if (! (bool) config('cashalot.enabled', false)) {
            return null;
        }

        if (! $this->hasRequiredConfig()) {
            return CashalotLog::create([
                'shop_order_id' => $order->id,
                'liqpay_log_id' => $sourceLog->liqpay_log_id,
                'status' => 'skipped',
                'error_code' => 'CONFIG_MISSING',
                'error_message' => 'Cashalot config values are missing',
                'check_sum' => $this->resolveCheckSum($order),
                'payment_type' => 'Cashalot return',
            ]);
        }

        $existingSuccess = CashalotLog::query()
            ->where('shop_order_id', $order->id)
            ->where('status', 'success')
            ->where('payment_type', 'Cashalot return')
            ->latest('id')
            ->first();

        if ($existingSuccess) {
            return $existingSuccess;
        }

        $originalPayload = is_array($sourceLog->request_payload) ? $sourceLog->request_payload : [];
        $check = $this->buildReturnCheckPayload($order, $sourceLog, $originalPayload);

        $log = CashalotLog::create([
            'shop_order_id' => $order->id,
            'liqpay_log_id' => $sourceLog->liqpay_log_id,
            'status' => 'pending',
            'check_sum' => (float) data_get($check, 'CHECKTOTAL.SUM', $this->resolveCheckSum($order)),
            'payment_type' => 'Cashalot return',
            'request_payload' => array_merge($check, [
                'StornedCheck' => $originalPayload,
            ]),
        ]);

        try {
            $response = app(CashalotApiClient::class)->registerCheck($check, [
                'storned_check' => $originalPayload,
            ]);

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

        $prro = $this->prroConfig();

        $consumerResponse = app(CashalotApiClient::class)->sendCheckToConsumer([
            'service_type' => $serviceType,
            'phone_number' => $phone,
            'registrar_num_fiscal' => (string) ($prro['numfiscal'] ?? ''),
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
        $prro = $this->prroConfig();

        return trim((string) ($prro['numfiscal'] ?? '')) !== ''
            && trim((string) ($prro['certificate'] ?? '')) !== ''
            && trim((string) ($prro['key'] ?? '')) !== ''
            && trim((string) ($prro['password'] ?? '')) !== '';
    }

    protected function resolveCheckSum(Order $order): float
    {
        return round(max(0, (float) ($order->grand_total ?? $order->total_price ?? 0)), 2);
    }

    protected function resolvePaymentTypeLabel(Order $order, array $liqpayPayload = []): string
    {
        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        return match ($payment) {
            PaymentMethodEnum::CASH => 'Готівка',
            PaymentMethodEnum::POS => 'POS-термінал',
            PaymentMethodEnum::LIQPAY => 'LiqPay',
            PaymentMethodEnum::PAYPARTS => 'Оплата частинами PrivatBank',
            default => $liqpayPayload !== [] ? 'LiqPay' : ($payment?->label('uk') ?? 'Невідомо'),
        };
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
            $name = $this->resolveItemName($item);

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

        return [
            'CHECKHEAD' => [
                'DOCTYPE' => 'SaleGoods',
                'DOCSUBTYPE' => 'CheckGoods',
                'TESTING' => $this->isTestingPayment($order, $liqpayPayload),
            ],
            'CHECKTOTAL' => [
                'SUM' => $total,
                'DISCOUNTSUM' => $effectiveDiscount,
            ],
            'CHECKPAY' => $this->buildCheckPay($order, $liqpayPayload, $total),
            'CHECKBODY' => $items,
        ];
    }

    protected function resolveItemName($item): string
    {
        $snapshotName = trim((string) data_get($item->product_snapshot, 'title'));
        if ($snapshotName !== '') {
            return $snapshotName;
        }

        $title = trim((string) ($item->title ?? ''));
        if ($title !== '') {
            return $title;
        }

        $product = $item->product;
        if ($product) {
            $displayName = trim((string) ($product->display_name ?? ''));
            if ($displayName !== '' && $displayName !== '—') {
                return $displayName;
            }

            if (method_exists($product, 'getTranslation')) {
                $name = trim((string) $product->getTranslation('title', 'uk', false));
                if ($name !== '') {
                    return $name;
                }
            }

            if ($product->parent && method_exists($product->parent, 'getTranslation')) {
                $name = trim((string) $product->parent->getTranslation('title', 'uk', false));
                if ($name !== '') {
                    return $name;
                }
            }

            $displayShort = trim((string) ($product->display_short ?? ''));
            if ($displayShort !== '' && $displayShort !== '—') {
                return $displayShort;
            }

            if (method_exists($product, 'adminBaseName')) {
                $name = trim((string) $product->adminBaseName('uk'));
                if ($name !== '') {
                    return $name;
                }
            }

            $slug = trim((string) ($product->slug ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }

        return 'Товар';
    }

    protected function buildCheckPay(Order $order, array $liqpayPayload, float $total): array
    {
        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if ($payment === PaymentMethodEnum::CASH) {
            return [
                [
                    'PAYFORMCD' => 0,
                    'PAYFORMNM' => 'Готівка',
                    'SUM' => $total,
                ],
            ];
        }

        if ($payment === PaymentMethodEnum::POS) {
            return [
                [
                    'PAYFORMCD' => 1,
                    'PAYFORMNM' => 'Банківська картка',
                    'SUM' => $total,
                    'PAYSYS' => [
                        [
                            'TAXNUM' => 'POS',
                            'NAME' => 'POS-термінал',
                            'SUM' => $total,
                            'COMMISSION' => 0,
                        ],
                    ],
                ],
            ];
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
        ];
    }

    protected function buildReturnCheckPayload(Order $order, CashalotLog $sourceLog, array $originalPayload): array
    {
        $payload = $originalPayload !== []
            ? $originalPayload
            : $this->buildRequestPayload($order, []);

        $payload['CHECKHEAD'] = array_merge(
            (array) ($payload['CHECKHEAD'] ?? []),
            [
                'DOCTYPE' => 'SaleGoods',
                'DOCSUBTYPE' => 'CheckStorno',
                'TESTING' => (bool) data_get($originalPayload, 'CHECKHEAD.TESTING', false),
            ]
        );

        $payload['CHECKTOTAL'] = array_merge(
            (array) ($payload['CHECKTOTAL'] ?? []),
            [
                'SUM' => $this->resolveCheckSum($order),
            ]
        );

        return $payload;
    }

    protected function isTestingPayment(Order $order, array $liqpayPayload): bool
    {
        $payment = $order->payment instanceof PaymentMethodEnum
            ? $order->payment
            : PaymentMethodEnum::tryFrom((int) $order->payment);

        if (in_array($payment, [PaymentMethodEnum::CASH, PaymentMethodEnum::POS], true)) {
            return false;
        }

        if ($payment === PaymentMethodEnum::PAYPARTS) {
            return false;
        }

        $status = mb_strtolower(trim((string) ($liqpayPayload['status'] ?? '')));

        if ($status === 'sandbox') {
            return true;
        }

        return $payment === PaymentMethodEnum::LIQPAY && (bool) config('liqpay.sandbox', false);
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
