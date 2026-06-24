<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Models\Shop\PaypartsBank;
use App\Models\Shop\PaypartsTransaction;
use Illuminate\Support\Facades\Http;

class PrivatBankPaypartsService
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('services.payparts.privatbank.base_url', 'https://payparts2.privatbank.ua'), '/')
        );
    }

    public static function callbackUrl(string $routeName): string
    {
        $path = parse_url(route($routeName, [], false), PHP_URL_PATH) ?: route($routeName, [], false);
        $publicUrl = trim((string) config('services.payparts.privatbank.public_url', ''));

        if ($publicUrl !== '') {
            return rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
        }

        return route($routeName, [], true);
    }

    public function createPayment(
        Order $order,
        PaypartsBank $bank,
        string $merchantType,
        int $partsCount,
        ?string $customerPhone = null,
        ?string $customerEmail = null,
        ?string $locale = null,
    ): PaypartsTransaction {
        $responseUrl = self::callbackUrl('payparts.response');
        $redirectUrl = self::callbackUrl('payparts.redirect');

        $orderNumber = 'order_' . $order->id;
        $amount = (float) round((float) $order->grand_total, 2);
        $products = $this->buildProductsPayload($order);
        $password = (string) $bank->account_password;
        $storeId = (string) $bank->store_id;
        $signature = $this->signRequest(
            $password,
            $storeId,
            $orderNumber,
            $amount,
            $partsCount,
            $merchantType,
            $responseUrl,
            $redirectUrl,
            $products
        );

        $payload = [
            'storeId' => $storeId,
            'orderId' => $orderNumber,
            'amount' => $amount,
            'partsCount' => $partsCount,
            'merchantType' => $merchantType,
            'products' => $products,
            'responseUrl' => $responseUrl,
            'redirectUrl' => $redirectUrl,
            'signature' => $signature,
        ];

        if ($customerPhone) {
            $payload['sendPhone'] = preg_replace('/\D+/', '', $customerPhone);
        }

        $apiResponse = Http::acceptJson()
            ->asJson()
            ->post($this->baseUrl . (string) config('services.payparts.privatbank.create_path', '/ipp/v2/payment/create'), $payload);

        $responseData = $apiResponse->json() ?: [];
        $hasToken = ! empty($responseData['token']);

        $transaction = PaypartsTransaction::create([
            'shop_order_id' => $order->id,
            'payparts_bank_id' => $bank->id,
            'status' => $apiResponse->successful() && $hasToken ? 'payment_redirected' : 'payment_failed',
            'merchant_type' => $merchantType,
            'parts_count' => $partsCount,
            'amount' => $amount,
            'order_id' => $orderNumber,
            'token' => $responseData['token'] ?? null,
            'signature' => $signature,
            'request_payload' => $payload,
            'response_payload' => $responseData,
            'response_message' => $responseData['message'] ?? null,
            'response_code' => $responseData['code'] ?? null,
            'redirect_url' => $redirectUrl,
            'response_url' => $responseUrl,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'customer_locale' => $locale,
        ]);

        if (! $apiResponse->successful()) {
            throw new \RuntimeException($responseData['message'] ?? 'PrivatBank payparts request failed');
        }

        if (! $hasToken) {
            throw new \RuntimeException('PrivatBank payparts token is missing');
        }

        return $transaction;
    }

    public function paymentUrl(string $token): string
    {
        return $this->baseUrl . (string) config('services.payparts.privatbank.payment_path', '/ipp/v2/payment') . '?token=' . urlencode($token);
    }

    public function decodeCallback(PaypartsBank $bank, string $data, string $signature): array
    {
        $expected = base64_encode(sha1((string) $bank->account_password . $data . (string) $bank->account_password, true));

        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid PrivatBank payparts signature');
        }

        return json_decode(base64_decode($data), true) ?: [];
    }

    public function verifyPaymentCallback(PaypartsBank $bank, array $payload): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        $message = (string) ($payload['message'] ?? '');
        $expected = base64_encode(sha1(
            (string) $bank->account_password
            . (string) ($payload['storeId'] ?? $payload['storeIdentifier'] ?? '')
            . (string) ($payload['orderId'] ?? '')
            . (string) ($payload['paymentState'] ?? '')
            . $message
            . (string) $bank->account_password,
            true
        ));

        return $signature !== '' && hash_equals($expected, $signature);
    }

    public function signRequest(
        string $password,
        string $storeId,
        string $orderId,
        float $amount,
        int $partsCount,
        string $merchantType,
        string $responseUrl,
        string $redirectUrl,
        array $products
    ): string {
        $productsString = $this->productsSignatureString($products);
        $amountString = $this->signatureNumber($amount);
        $payload = $password
            . $storeId
            . $orderId
            . $amountString
            . $partsCount
            . $merchantType
            . $responseUrl
            . $redirectUrl
            . $productsString
            . $password;

        return base64_encode(sha1($payload, true));
    }

    protected function buildProductsPayload(Order $order): array
    {
        $items = $order->loadMissing(['items.product'])->items;

        $products = $items->map(function ($item): array {
            $qty = max(1, (int) ($item->qty ?? 1));
            $unitPrice = (float) ($item->unit_price ?? 0);

            return [
                'name' => trim((string) ($item->product?->name ?? $item->product?->title ?? $item->sku ?? 'Item')),
                'count' => $qty,
                'price' => round($unitPrice, 2),
            ];
        })->values()->all();

        $productsTotal = collect($products)->sum(
            fn (array $product): float => (float) ($product['count'] ?? 1) * (float) ($product['price'] ?? 0)
        );
        $amount = round((float) $order->grand_total, 2);
        $diff = round($amount - $productsTotal, 2);

        if ($diff > 0) {
            $products[] = [
                'name' => 'Dostavka',
                'count' => 1,
                'price' => $diff,
            ];
        } elseif ($diff < 0 && $products !== []) {
            $lastIndex = array_key_last($products);
            $lastCount = max(1, (int) ($products[$lastIndex]['count'] ?? 1));
            $products[$lastIndex]['price'] = round(
                (float) $products[$lastIndex]['price'] + ($diff / $lastCount),
                2
            );
        }

        return $products;
    }

    protected function productsSignatureString(array $products): string
    {
        return collect($products)
            ->map(fn (array $product): string => (string) ($product['name'] ?? '')
                . (string) ($product['count'] ?? '')
                . $this->signatureNumber((float) ($product['price'] ?? 0)))
            ->implode('');
    }

    protected function signatureNumber(float|int $value): string
    {
        return preg_replace('/\D+/', '', number_format((float) $value, 2, '.', ''));
    }
}
