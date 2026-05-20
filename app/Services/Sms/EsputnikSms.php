<?php

namespace App\Services\Sms;

use App\Models\Shop\SmsLog;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LogicException;

class EsputnikSms
{
    public function __construct(
        private readonly Client $http = new Client(['base_uri' => 'https://esputnik.com'])
    ) {}

    public function sendCode(string $phone, string $code, ?string $from = null, array $context = []): array
    {
        $from ??= config('services.esputnik.from', env('ESPUTNIK_SMS_FROM', 'TRIPIROGI'));
        $login = config('services.esputnik.login');
        $password = config('services.esputnik.password');

        $phone = $this->normalizeUa($phone);
        $messageText = "Привіт! Твій код для авторизації: {$code}";
        $payload = [
            'from' => $from,
            'text' => $messageText,
            'phoneNumbers' => [$phone],
        ];

        $smsLog = SmsLog::create([
            'client_id' => Arr::get($context, 'client_id'),
            'channel' => 'esputnik',
            'message_type' => Arr::get($context, 'message_type'),
            'phone' => (string) Arr::get($context, 'raw_phone', $phone),
            'normalized_phone' => $phone,
            'sender' => $from,
            'message_preview' => $this->maskMessagePreview($messageText),
            'message_text' => $messageText,
            'context' => $context !== [] ? $context : null,
            'provider_payload' => [
                'from' => $from,
                'phoneNumbers' => [$phone],
            ],
        ]);

        try {
            $resp = $this->http->post('/api/v1/message/sms', [
                'auth' => [$login, $password],
                'json' => $payload,
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
                'timeout' => 10,
            ]);

            $status = $resp->getStatusCode();
            $body = (string) $resp->getBody();
            $decodedBody = json_decode($body, true);

            $smsLog->update([
                'http_status' => $status,
                'success' => $status < 300,
                'provider_request_id' => data_get($decodedBody, 'results.requestId'),
                'provider_status' => data_get($decodedBody, 'results.status'),
                'provider_response' => is_array($decodedBody) ? $decodedBody : ['raw' => $body],
                'error_message' => $status >= 300 ? $body : null,
            ]);

            if ($status >= 300) {
                Log::error('SMS send failed', [
                    'sms_log_id' => $smsLog->id,
                    'phone' => $phone,
                    'status' => $status,
                ]);
            }

            return [
                'status' => $status,
                'body' => $body,
                'decoded' => $decodedBody,
                'request_id' => data_get($decodedBody, 'results.requestId'),
                'provider_status' => data_get($decodedBody, 'results.status'),
                'log_id' => $smsLog->id,
            ];
        } catch (\Exception $e) {
            $smsLog->update([
                'http_status' => 500,
                'success' => false,
                'error_message' => $e->getMessage(),
                'provider_response' => ['exception' => $e->getMessage()],
            ]);

            Log::error('SMS send exception', [
                'sms_log_id' => $smsLog->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'body' => $e->getMessage(),
                'decoded' => null,
                'request_id' => null,
                'provider_status' => null,
                'log_id' => $smsLog->id,
            ];
        }
    }

    public function checkStatus(SmsLog $smsLog): array
    {
        $statusUrl = config('services.esputnik.sms_status_url');
        if (! $statusUrl) {
            throw new LogicException('SMS status API endpoint is not configured.');
        }

        if (! $smsLog->provider_request_id) {
            throw new LogicException('SMS log does not have provider request ID.');
        }

        $login = config('services.esputnik.login');
        $password = config('services.esputnik.password');

        $resp = $this->http->post($statusUrl, [
            'auth' => [$login, $password],
            'json' => ['requestId' => $smsLog->provider_request_id],
            'headers' => ['Accept' => 'application/json'],
            'http_errors' => false,
            'timeout' => 10,
        ]);

        $status = $resp->getStatusCode();
        $body = (string) $resp->getBody();
        $decodedBody = json_decode($body, true);

        $smsLog->update([
            'provider_status' => data_get($decodedBody, 'results.status', $smsLog->provider_status),
            'delivery_status' => data_get($decodedBody, 'results.deliveryStatus', data_get($decodedBody, 'results.delivery.status')),
            'delivery_checked_at' => now(),
            'provider_response' => is_array($decodedBody) ? $decodedBody : ['raw' => $body],
            'error_message' => $status >= 300 ? $body : $smsLog->error_message,
        ]);

        return [
            'status' => $status,
            'body' => $body,
            'decoded' => $decodedBody,
        ];
    }

    private function normalizeUa(string $raw): string
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if (str_starts_with($d, '0')) {
            $d = '38' . $d;
        }
        if (strlen($d) === 9) {
            $d = '380' . $d;
        }

        return $d;
    }

    private function maskMessagePreview(string $messageText): string
    {
        $masked = preg_replace('/\d{4,}/', '****', $messageText);

        return mb_substr((string) $masked, 0, 255);
    }
}
