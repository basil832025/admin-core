<?php

namespace App\Services\PrintNode;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PrintNodeService
{
    private const DEFAULT_API_ORIGIN = 'http://printservice.test';

    public function isEnabled(): bool
    {
        $enabled = (bool) Setting::admin('printservice.enabled', Setting::admin('printnode.enabled', false));

        return $enabled
            && $this->getBaseUrl() !== ''
            && $this->getApiKey() !== '';
    }

    public function getBaseUrl(): string
    {
        $apiBaseUrl = trim((string) Setting::admin('printservice.api_base_url', ''));

        if ($apiBaseUrl === '') {
            $apiBaseUrl = trim((string) env('PRINTSERVICE_API_BASE_URL', self::DEFAULT_API_ORIGIN));
        }

        return $this->normalizeApiBaseUrl($apiBaseUrl);
    }

    public function getTenantCode(): string
    {
        $tenantCode = trim((string) Setting::admin('printservice.tenant_code', ''));

        if ($tenantCode !== '') {
            return $tenantCode;
        }

        return trim((string) env('PRINTSERVICE_TENANT_CODE', 'default'));
    }

    public function getApiKey(): string
    {
        $apiKey = trim((string) Setting::admin('printservice.api_key', ''));

        if ($apiKey !== '') {
            return $apiKey;
        }

        return trim((string) env('PRINTSERVICE_API_KEY', ''));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPrinters(bool $fresh = false): array
    {
        return [];
    }

    public function resolvePrinterSelector(?int $configuredId = null, ?string $configuredName = null): ?string
    {
        if (! empty($configuredId) && $configuredId > 0) {
            return (string) $configuredId;
        }

        $name = trim((string) $configuredName);
        if ($name !== '') {
            return $name;
        }

        $fromNewKey = trim((string) Setting::admin('printservice.printer_selector', ''));
        if ($fromNewKey !== '') {
            return $fromNewKey;
        }

        $fromLegacyPrintservice = trim((string) Setting::admin('printservice.printer_name', ''));
        if ($fromLegacyPrintservice !== '') {
            return $fromLegacyPrintservice;
        }

        $fromLegacyPrintnode = trim((string) Setting::admin('printnode.printer_name', ''));
        if ($fromLegacyPrintnode !== '') {
            return $fromLegacyPrintnode;
        }

        return 'default';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPrinterById(int $printerId): ?array
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createRawPrintJob(string $printerSelector, string $title, string $rawContent, int $qty = 1, array $options = []): array
    {
        $payload = [
            'tenant_code' => $this->getTenantCode(),
            'printer_selector' => $printerSelector,
            'job_type' => 'raw',
            'content_type' => 'text/plain',
            'payload' => $rawContent,
            'copies' => max(1, $qty),
            'priority' => 50,
            'idempotency_key' => 'myadmin-raw-'.sha1($title.'|'.$printerSelector.'|'.substr($rawContent, 0, 128).'|'.time()),
        ];

        if ($options !== []) {
            $payload['meta'] = $options;
        }

        $response = $this->request('post', '/jobs', $payload);
        $data = $response->json();

        return [
            'printjob_id' => (string) ($data['job_id'] ?? ''),
            'status' => $response->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPdfBase64PrintJob(string $printerSelector, string $title, string $pdfBinary, int $qty = 1, array $options = []): array
    {
        $payload = [
            'tenant_code' => $this->getTenantCode(),
            'printer_selector' => $printerSelector,
            'job_type' => 'raw',
            'content_type' => 'application/pdf;base64',
            'payload' => base64_encode($pdfBinary),
            'copies' => max(1, $qty),
            'priority' => 50,
            'idempotency_key' => 'myadmin-pdf-'.sha1($title.'|'.$printerSelector.'|'.strlen($pdfBinary).'|'.time()),
        ];

        if ($options !== []) {
            $payload['meta'] = $options;
        }

        $response = $this->request('post', '/jobs', $payload);
        $data = $response->json();

        return [
            'printjob_id' => (string) ($data['job_id'] ?? ''),
            'status' => $response->status(),
        ];
    }

    private function request(string $method, string $uri, array $payload = []): Response
    {
        $baseUrl = $this->getBaseUrl();
        if ($baseUrl === '') {
            throw new \RuntimeException('PrintService API base URL is not configured.');
        }

        $url = $baseUrl . $uri;

        $http = Http::timeout(20)
            ->acceptJson()
            ->withOptions([
                'verify' => $this->shouldVerifyTlsPeer($baseUrl),
            ]);

        $apiKey = $this->getApiKey();
        if ($apiKey !== '') {
            $http = $http->withToken($apiKey);
        }

        $response = $method === 'get'
            ? $http->get($url)
            : $http->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'PrintService request failed [%s] %s',
                $response->status(),
                $response->body()
            ));
        }

        return $response;
    }

    private function normalizeApiBaseUrl(string $value): string
    {
        $base = rtrim(trim($value), '/');
        if ($base === '') {
            return '';
        }

        if (str_ends_with($base, '/api/print/v1')) {
            return $base;
        }

        return $base.'/api/print/v1';
    }

    private function shouldVerifyTlsPeer(string $baseUrl): bool
    {
        $configured = Setting::admin('printservice.verify_tls_peer', null);
        if ($configured !== null) {
            return $this->toBool($configured, true);
        }

        $env = env('PRINTSERVICE_VERIFY_TLS', null);
        if ($env !== null) {
            return $this->toBool($env, true);
        }

        $host = (string) parse_url($baseUrl, PHP_URL_HOST);
        if (app()->environment('local') && str_ends_with($host, '.test')) {
            return false;
        }

        return true;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}
