<?php

namespace App\Services\Sms;

use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EsputnikSms
{
    public function __construct(
        private readonly Client $http = new Client(['base_uri' => 'https://esputnik.com'])
    ) {}

public function sendCode(string $phone, string $code, ?string $from = null): array
{
    $from ??= config('services.esputnik.from', env('ESPUTNIK_SMS_FROM', 'TRIPIROGI'));
    $login = config('services.esputnik.login');
    $password = config('services.esputnik.password');

    // eSputnik требует международный формат без "+"
    $phone = $this->normalizeUa($phone);

    // Логируем попытку отправки
    Log::info('Попытка отправки SMS', [
        'phone' => $phone,
        'from' => $from,
        'login_set' => !empty($login),
        'password_set' => !empty($password),
    ]);

    try {
        $resp = $this->http->post('/api/v1/message/sms', [
            'auth' => [$login, $password],
            'json' => [
                'from'         => $from,
                'text'         => "Привіт! Твій код для авторизації: {$code}",
                'phoneNumbers' => [$phone],
            ],
            'headers' => ['Accept' => 'application/json'],
            'http_errors' => false,
            'timeout' => 10,
        ]);

        $status = $resp->getStatusCode();
        $body = (string) $resp->getBody();

        // Логируем результат
        if ($status >= 300) {
            Log::error('SMS отправка завершилась ошибкой', [
                'phone' => $phone,
                'status' => $status,
                'body' => $body,
            ]);
        } else {
            Log::info('SMS отправлено успешно', [
                'phone' => $phone,
                'status' => $status,
                'body' => $body,
            ]);
        }

        return [
            'status' => $status,
            'body'   => $body,
        ];
    } catch (\Exception $e) {
        Log::error('Исключение при отправке SMS', [
            'phone' => $phone,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return [
            'status' => 500,
            'body'   => $e->getMessage(),
        ];
    }
}

private function normalizeUa(string $raw): string
{
    $d = preg_replace('/\D+/', '', (string)$raw);
    if (Str::startsWith($d, '0')) $d = '38' . $d;
    if (strlen($d) === 9) $d = '380' . $d;
    return $d;
}
}
