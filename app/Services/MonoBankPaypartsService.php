<?php

namespace App\Services;

use App\Models\Shop\Order;
use App\Models\Shop\PaypartsBank;
use App\Models\Shop\PaypartsTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MonoBankPaypartsService
{
    public function __construct(
        private readonly string $baseUrl,
    ) {}

    public static function make(): self
    {
        return new self(
            rtrim((string) config('services.payparts.monobank.base_url', 'https://u2-demo-ext.mono.st4g3.com'), '/')
        );
    }

    public static function callbackUrl(string $routeName, array $query = []): string
    {
        $path = parse_url(route($routeName, [], false), PHP_URL_PATH) ?: route($routeName, [], false);
        $publicUrl = trim((string) config('services.payparts.monobank.public_url', ''));

        if ($publicUrl !== '') {
            $url = rtrim($publicUrl, '/') . '/' . ltrim($path, '/');
        } else {
            $url = route($routeName, [], true);
        }

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
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
        $storeOrderId = $this->buildStoreOrderId($order);
        $callbackUrl = self::callbackUrl('payparts.monobank.response');
        $amount = (float) round((float) $order->grand_total, 2);
        $programType = $this->normalizeProgramType($merchantType);
        $payload = [
            'store_order_id' => $storeOrderId,
            'client_phone' => $this->normalizePhone((string) ($customerPhone ?: $order->clients?->phone ?? '')),
            'total_sum' => $amount,
            'invoice' => [
                'date' => now()->toDateString(),
                'number' => (string) $order->id,
                'source' => 'INTERNET',
            ],
            'available_programs' => [[
                'available_parts_count' => [$partsCount],
                'type' => $programType,
            ]],
            'products' => $this->buildProductsPayload($order),
            'result_callback' => $callbackUrl,
        ];

        $response = $this->post($bank, (string) config('services.payparts.monobank.create_path', '/api/order/create'), $payload);
        $responseData = $response->json();
        $responseData = is_array($responseData) ? $responseData : [];
        if ($responseData === [] && trim($response->body()) !== '') {
            $responseData = ['raw_body' => $response->body()];
        }
        $providerOrderId = (string) ($responseData['order_id'] ?? '');

        $transaction = PaypartsTransaction::create([
            'shop_order_id' => $order->id,
            'payparts_bank_id' => $bank->id,
            'status' => $response->successful() && $providerOrderId !== '' ? 'pending_payment' : 'payment_failed',
            'merchant_type' => $merchantType !== '' ? $merchantType : 'product_1',
            'parts_count' => $partsCount,
            'amount' => $amount,
            'order_id' => $storeOrderId,
            'token' => $providerOrderId !== '' ? $providerOrderId : null,
            'signature' => $this->signatureForPayload($bank, $payload),
            'request_payload' => $payload,
            'response_payload' => $responseData,
            'response_message' => $this->responseMessage($responseData),
            'response_code' => $responseData['code'] ?? (string) $response->status(),
            'redirect_url' => null,
            'response_url' => $callbackUrl,
            'customer_phone' => $customerPhone,
            'customer_email' => $customerEmail,
            'customer_locale' => $locale,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->responseMessage($responseData) ?? ('Monobank payparts request failed with HTTP ' . $response->status()));
        }

        if ($providerOrderId === '') {
            throw new \RuntimeException('Monobank payparts order_id is missing');
        }

        return $transaction;
    }

    public function fetchPaymentState(PaypartsTransaction $transaction): array
    {
        $transaction->loadMissing('bank');
        $bank = $transaction->bank;

        if (! $bank) {
            throw new \RuntimeException('Payparts bank is missing for status sync');
        }

        $payload = ['order_id' => (string) $transaction->token];
        $response = $this->post($bank, (string) config('services.payparts.monobank.state_path', '/api/order/state'), $payload);
        $responsePayload = $response->json();
        $responsePayload = is_array($responsePayload) ? $responsePayload : [];

        if (! $response->successful()) {
            throw new \RuntimeException('Monobank payparts state request failed with HTTP ' . $response->status());
        }

        return [
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
        ];
    }

    public function confirmPayment(PaypartsTransaction $transaction): array
    {
        $transaction->loadMissing('bank');
        $bank = $transaction->bank;

        if (! $bank) {
            throw new \RuntimeException('Payparts bank is missing for confirm');
        }

        $payload = ['order_id' => (string) $transaction->token];
        $response = $this->post($bank, (string) config('services.payparts.monobank.confirm_path', '/api/order/confirm'), $payload);
        $responsePayload = $response->json();
        $responsePayload = is_array($responsePayload) ? $responsePayload : [];

        if (! $response->successful()) {
            throw new \RuntimeException('Monobank payparts confirm request failed with HTTP ' . $response->status());
        }

        return [
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
        ];
    }

    public function returnPayment(
        PaypartsTransaction $transaction,
        string $storeReturnId,
        float $amount,
        bool $returnMoneyToCard = true,
    ): array {
        $transaction->loadMissing('bank');
        $bank = $transaction->bank;

        if (! $bank) {
            throw new \RuntimeException('Payparts bank is missing for return');
        }

        $providerOrderId = (string) $transaction->token;
        if ($providerOrderId === '') {
            throw new \RuntimeException('Monobank provider order_id is missing for return');
        }

        $payload = [
            'order_id' => $providerOrderId,
            'sum' => round($amount, 2),
            'store_return_id' => $storeReturnId,
            'return_money_to_card' => $returnMoneyToCard,
        ];
        $response = $this->post($bank, (string) config('services.payparts.monobank.return_path', '/api/order/return'), $payload);
        $responsePayload = $response->json();
        $responsePayload = is_array($responsePayload) ? $responsePayload : [];

        if (! $response->successful()) {
            throw new \RuntimeException(
                $this->responseMessage($responsePayload)
                    ?? ('Monobank payparts return request failed with HTTP ' . $response->status())
            );
        }

        return [
            'request_payload' => $payload,
            'response_payload' => $responsePayload,
        ];
    }

    public function verifySignature(PaypartsBank $bank, string $rawBody, string $signature): bool
    {
        return $signature !== '' && hash_equals($this->signRawBody((string) $bank->account_password, $rawBody), $signature);
    }

    public function normalizeRemoteStatus(array $payload): string
    {
        $state = strtoupper((string) ($payload['state'] ?? ''));
        $subState = strtoupper((string) ($payload['order_sub_state'] ?? ''));

        if ($state === 'SUCCESS' && in_array($subState, ['ACTIVE', 'DONE'], true)) {
            return 'payment_success';
        }

        if ($state === 'SUCCESS' && $subState === 'RETURNED') {
            return 'refunded';
        }

        if ($state === 'FAIL') {
            return 'payment_failed';
        }

        return 'pending_payment';
    }

    public function shouldConfirm(array $payload): bool
    {
        return strtoupper((string) ($payload['state'] ?? '')) === 'IN_PROCESS'
            && strtoupper((string) ($payload['order_sub_state'] ?? '')) === 'WAITING_FOR_STORE_CONFIRM';
    }

    protected function responseMessage(array $responseData): ?string
    {
        $message = $responseData['message'] ?? $responseData['raw_body'] ?? null;

        return is_string($message) && $message !== '' ? mb_substr(strip_tags($message), 0, 250) : null;
    }

    protected function normalizeProgramType(string $merchantType): string
    {
        $merchantType = trim($merchantType);

        if ($merchantType === '' || $merchantType === 'product_1') {
            return 'payment_installments';
        }

        return $merchantType;
    }
    protected function post(PaypartsBank $bank, string $path, array $payload): Response
    {
        $body = $this->encodePayload($payload);
        $url = $this->baseUrl . $path;

        Log::info('Monobank PayParts outgoing request', [
            'method' => 'POST',
            'base_url' => $this->baseUrl,
            'endpoint' => $path,
            'full_url' => $url,
            'store_id' => (string) $bank->store_id,
            'payload_keys' => array_keys($payload),
            'store_order_id' => (string) ($payload['store_order_id'] ?? ''),
        ]);

        $response = Http::withoutRedirecting()
            ->withHeaders([
                'store-id' => (string) $bank->store_id,
                'signature' => $this->signRawBody((string) $bank->account_password, $body),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->withOptions([
                'proxy' => '',
                'curl' => [
                    CURLOPT_PROXY => '',
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ])
            ->timeout(20)
            ->post($url);

        Log::info('Monobank PayParts raw response', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => mb_substr($response->body(), 0, 2000),
        ]);

        return $response;
    }

    protected function signatureForPayload(PaypartsBank $bank, array $payload): string
    {
        return $this->signRawBody((string) $bank->account_password, $this->encodePayload($payload));
    }

    protected function signRawBody(string $secret, string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    protected function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
                'sum' => round($unitPrice, 2),
            ];
        })->values()->all();

        $productsTotal = collect($products)->sum(
            fn (array $product): float => (float) ($product['count'] ?? 1) * (float) ($product['sum'] ?? 0)
        );
        $amount = round((float) $order->grand_total, 2);
        $diff = round($amount - $productsTotal, 2);

        if ($diff > 0) {
            $products[] = [
                'name' => 'Dostavka',
                'count' => 1,
                'sum' => $diff,
            ];
        } elseif ($diff < 0 && $products !== []) {
            $lastIndex = array_key_last($products);
            $lastCount = max(1, (int) ($products[$lastIndex]['count'] ?? 1));
            $products[$lastIndex]['sum'] = round(
                (float) $products[$lastIndex]['sum'] + ($diff / $lastCount),
                2
            );
        }

        return $products;
    }

    protected function buildStoreOrderId(Order $order): string
    {
        return sprintf(
            'order_%d_%s_%03d',
            (int) $order->id,
            now()->format('YmdHis'),
            random_int(100, 999)
        );
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }

        if (strlen($digits) === 9) {
            $digits = '380' . $digits;
        }

        return '+' . ltrim($digits, '+');
    }
}
