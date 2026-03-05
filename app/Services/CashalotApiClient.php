<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CashalotApiClient
{
    public function registerCheck(array $check, array $options = []): array
    {
        $payload = [
            'Command' => 'RegisterCheck',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) config('cashalot.numfiscal', ''),
            'Check' => $check,
            'AutoOpenShift' => (bool) Arr::get($options, 'auto_open_shift', config('cashalot.auto_open_shift', true)),
            'GetQrCode' => (bool) Arr::get($options, 'get_qr_code', config('cashalot.get_qr_code', true)),
            'QrSize' => max(256, min(2048, (int) Arr::get($options, 'qr_size', config('cashalot.qr_size', 512)))),
            'Visualization' => (bool) Arr::get($options, 'visualization', config('cashalot.visualization', true)),
            'VisAsHtml' => (bool) Arr::get($options, 'vis_as_html', config('cashalot.vis_as_html', false)),
        ];

        if (isset($options['check_to_convert'])) {
            $payload['CheckToConvert'] = (string) $options['check_to_convert'];
        }

        if (isset($options['storned_check']) && is_array($options['storned_check'])) {
            $payload['StornedCheck'] = $options['storned_check'];
        }

        if (isset($options['storned_check_to_convert'])) {
            $payload['StornedCheckToConvert'] = (string) $options['storned_check_to_convert'];
        }

        return $this->send($payload);
    }

    public function transactionsRegistrarState(array $options = []): array
    {
        return $this->send([
            'Command' => 'TransactionsRegistrarState',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) config('cashalot.numfiscal', ''),
        ]);
    }

    public function openShift(array $options = []): array
    {
        return $this->send([
            'Command' => 'OpenShift',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) config('cashalot.numfiscal', ''),
        ]);
    }

    public function sendCheckToConsumer(array $payload): array
    {
        return $this->send([
            'Command' => 'SendCheckToConsumer',
            'UID' => (string) ($payload['uid'] ?? Str::uuid()),
            'ServiceType' => (int) ($payload['service_type'] ?? config('cashalot.consumer_service_type', 0)),
            'PhoneNumber' => (string) ($payload['phone_number'] ?? ''),
            'RegistrarNumFiscal' => (string) ($payload['registrar_num_fiscal'] ?? config('cashalot.numfiscal', '')),
            'OrderNumFiscal' => (string) ($payload['order_num_fiscal'] ?? ''),
            'OrderSum' => (float) ($payload['order_sum'] ?? 0),
            'OrderDateTime' => (string) ($payload['order_date_time'] ?? ''),
        ]);
    }

    protected function send(array $payload): array
    {
        $requestPayload = array_merge($this->authPayload(), $payload);

        $response = Http::timeout((int) config('cashalot.timeout', 20))
            ->acceptJson()
            ->asJson()
            ->post((string) config('cashalot.api_url', 'https://fsapi.cashalot.org.ua/'), $requestPayload);

        if (! $response->ok()) {
            return [
                'ErrorCode' => 'HTTP_' . $response->status(),
                'ErrorMessage' => $response->body(),
            ];
        }

        $decoded = $response->json();
        if (is_array($decoded)) {
            return $decoded;
        }

        return [
            'ErrorCode' => 'INVALID_JSON',
            'ErrorMessage' => 'Unexpected response format from Cashalot API',
        ];
    }

    protected function authPayload(): array
    {
        $payload = [];

        $certificate = trim((string) config('cashalot.certificate', ''));
        $privateKey = trim((string) config('cashalot.key', ''));
        $password = (string) config('cashalot.password', '');
        $useSmartId = (bool) config('cashalot.use_smart_id', false);
        $keyPin = trim((string) config('cashalot.key_pin', ''));

        if ($certificate !== '') {
            $payload['Certificate'] = $certificate;
        }

        if ($privateKey !== '') {
            $payload['PrivateKey'] = $privateKey;
        }

        if ($password !== '') {
            $payload['Password'] = $password;
        }

        if ($useSmartId) {
            $payload['UseSmartId'] = true;
        }

        if ($keyPin !== '') {
            $payload['KeyPin'] = $keyPin;
        }

        return $payload;
    }
}
