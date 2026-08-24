<?php

namespace App\Services;

use App\Models\Shop\Order;
use Illuminate\Support\Facades\Route;
use LiqPay;

class LiqPayService
{
    public function __construct(
        protected string $publicKey,
        protected string $privateKey,
        protected bool $sandbox,
        protected ?string $publicBaseUrl,
    ) {}

    public static function make(): self
    {
        return new self(
            config('liqpay.public_key'),
            config('liqpay.private_key'),
            (bool) config('liqpay.sandbox'),
            config('liqpay.public_base_url') ? rtrim((string) config('liqpay.public_base_url'), '/') : null,
        );
    }

    protected function client(): LiqPay
    {
        return new LiqPay($this->publicKey, $this->privateKey);
    }

    public function formForOrder(Order $order, string $lang = 'uk'): string
    {
        $liqpay = $this->client();

        $lang = in_array($lang, ['uk', 'ru', 'en'], true) ? $lang : 'uk';
        $description = match ($lang) {
            'ru' => 'Оплата заказа №' . $order->id,
            'en' => 'Order payment #' . $order->id,
            default => 'Оплата замовлення №' . $order->id,
        };

        $resultRoute = in_array($lang, ['ru', 'en'], true) && Route::has('localized.checkout.success')
            ? ['localized.checkout.success', ['locale' => $lang, 'order' => $order]]
            : ['checkout.success', ['order' => $order]];

        $params = [
            'action' => 'pay',
            'amount' => $order->grand_total,
            'currency' => 'UAH',
            'description' => $description,
            'order_id' => 'order_' . $order->id,
            'version' => '3',
            'result_url' => $this->routeUrl($resultRoute[0], $resultRoute[1]),
            'server_url' => $this->routeUrl('liqpay.callback'),
            'language' => $lang,
        ];

        if ($this->sandbox) {
            $params['sandbox'] = 1;
        }

        return $liqpay->cnb_form($params);
    }

    protected function routeUrl(string $name, array $parameters = []): string
    {
        if ($this->publicBaseUrl) {
            return $this->publicBaseUrl . route($name, $parameters, false);
        }

        return route($name, $parameters, true);
    }

    public function statusForOrder(Order $order): array
    {
        $response = $this->client()->api('request', [
            'action' => 'status',
            'version' => 3,
            'order_id' => 'order_' . $order->id,
        ]);

        return json_decode(json_encode($response), true) ?: [];
    }

    public function decodeCallback(string $data, string $signature): array
    {
        $expected = base64_encode(sha1(
            $this->privateKey . $data . $this->privateKey,
            true
        ));

        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid LiqPay signature');
        }

        return json_decode(base64_decode($data), true) ?? [];
    }
}
