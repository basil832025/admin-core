<?php
// app/Services/LiqPayService.php
namespace App\Services;

use App\Models\Shop\Order;
use LiqPay;

class LiqPayService
{
    public function __construct(
        protected string $publicKey,
        protected string $privateKey,
        protected bool $sandbox,
    ) {}

    public static function make(): self
    {
        return new self(
            config('liqpay.public_key'),
            config('liqpay.private_key'),
            (bool) config('liqpay.sandbox')
        );
    }

    protected function client(): LiqPay
    {
        return new LiqPay($this->publicKey, $this->privateKey);
    }

    /** HTML-форма c кнопкой LiqPay для заказа */
    public function formForOrder(Order $order, string $lang = 'uk'): string
    {
        $liqpay = $this->client();

        $params = [
            'action'      => 'pay',
            'amount'      => $order->grand_total,
            'currency'    => 'UAH',
            'description' => 'Оплата замовлення №'.$order->id,
            'order_id'    => 'order_'.$order->id,
            'version'     => '3',
            'result_url'  => route('checkout.success', $order, true),
            'server_url'  => route('liqpay.callback', [], true),
           // 'server_url'  => 'https://jaxson-semipreserved-judgmentally.ngrok-free.dev/liqpay/callback',
            'language'    => $lang,
        ];

        if ($this->sandbox) {
            $params['sandbox'] = 1;
        }
     //   \Log::info('LiqPay params', $params);
       // dd($params);
        // SDK отдаёт готовую <form>…</form> с кнопкой
        return $liqpay->cnb_form($params);
    }

    /** Разбор callback + проверка подписи */
    public function decodeCallback(string $data, string $signature): array
    {
        $expected = base64_encode(sha1(
            $this->privateKey.$data.$this->privateKey,
            true
        ));

        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Invalid LiqPay signature');
        }

        return json_decode(base64_decode($data), true) ?? [];
    }
}
