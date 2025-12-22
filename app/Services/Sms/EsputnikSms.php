<?php

namespace App\Services\Sms;

use GuzzleHttp\Client;
use Illuminate\Support\Str;

class EsputnikSms
{
    public function __construct(
        private readonly Client $http = new Client(['base_uri' => 'https://esputnik.com'])
    ) {}

public function sendCode(string $phone, string $code, ?string $from = null): array
{
    $from ??= config('services.esputnik.from', env('ESPUTNIK_SMS_FROM', 'TRIPIROGI'));

    // eSputnik требует международный формат без "+"
    $phone = $this->normalizeUa($phone);

    $resp = $this->http->post('/api/v1/message/sms', [
        'auth' => [config('services.esputnik.login'), config('services.esputnik.password')],
        'json' => [
            'from'         => $from,
            'text'         => "Привіт! Твій код для авторизації: {$code}",
            'phoneNumbers' => [$phone],
        ],
        'headers' => ['Accept' => 'application/json'],
        'http_errors' => false,
        'timeout' => 10,
    ]);

    return [
        'status' => $resp->getStatusCode(),
        'body'   => (string) $resp->getBody(),
    ];
}

private function normalizeUa(string $raw): string
{
    $d = preg_replace('/\D+/', '', (string)$raw);
    if (Str::startsWith($d, '0')) $d = '38' . $d;
    if (strlen($d) === 9) $d = '380' . $d;
    return $d;
}
}
