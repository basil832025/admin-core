<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CashalotApiClient
{
    protected function prroConfig(): array
    {
        return app(PrroConfigService::class)->getForCashalot();
    }

    public function registerCheck(array $check, array $options = []): array
    {
        $prro = $this->prroConfig();

        $payload = [
            'Command' => 'RegisterCheck',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
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
        $prro = $this->prroConfig();

        return $this->send([
            'Command' => 'TransactionsRegistrarState',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
        ]);
    }

    public function openShift(array $options = []): array
    {
        $prro = $this->prroConfig();

        return $this->send([
            'Command' => 'OpenShift',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
        ]);
    }

    public function closeShift(bool $zRepAuto = true, bool $visualization = true, array $options = []): array
    {
        $prro = $this->prroConfig();

        return $this->send([
            'Command' => 'CloseShift',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
            'ZRepAuto' => $zRepAuto,
            'Visualization' => $visualization,
        ]);
    }

    /**
     * Register a Z-report. If ZRep is omitted (null), Cashalot will generate it automatically.
     */
    public function registerZRep(?array $zRep = null, bool $visualization = true, array $options = []): array
    {
        $prro = $this->prroConfig();

        $payload = [
            'Command' => 'RegisterZRep',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
            'Visualization' => $visualization,
        ];

        if (is_array($zRep)) {
            $payload['ZRep'] = $zRep;
        } else {
            $payload['ZRep'] = null;
        }

        return $this->send($payload);
    }

    /**
     * Cleanup local registrar state in Cashalot module.
     * When $remove is false, Cashalot may auto-close shift if open.
     */
    public function cleanup(bool $remove = false, bool $visualization = true, array $options = []): array
    {
        $prro = $this->prroConfig();

        return $this->send([
            'Command' => 'Cleanup',
            'UID' => (string) ($options['uid'] ?? Str::uuid()),
            'NumFiscal' => (string) ($prro['numfiscal'] ?? ''),
            'Remove' => $remove,
            'Visualization' => $visualization,
        ]);
    }

    public function sendCheckToConsumer(array $payload): array
    {
        $prro = $this->prroConfig();

        return $this->send([
            'Command' => 'SendCheckToConsumer',
            'UID' => (string) ($payload['uid'] ?? Str::uuid()),
            'ServiceType' => (int) ($payload['service_type'] ?? config('cashalot.consumer_service_type', 0)),
            'PhoneNumber' => (string) ($payload['phone_number'] ?? ''),
            'RegistrarNumFiscal' => (string) ($payload['registrar_num_fiscal'] ?? ($prro['numfiscal'] ?? '')),
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

    /**
     * Build final request payload (auth + command payload).
     * Useful for logging/auditing.
     */
    public function buildRequestPayload(array $payload): array
    {
        return array_merge($this->authPayload(), $payload);
    }

    protected function authPayload(): array
    {
        $payload = [];
        $prro = $this->prroConfig();

        $certificate = trim((string) ($prro['certificate'] ?? ''));
        $privateKey = trim((string) ($prro['key'] ?? ''));
        $password = (string) ($prro['password'] ?? '');
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
