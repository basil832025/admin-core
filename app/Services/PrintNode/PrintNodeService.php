<?php

namespace App\Services\PrintNode;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PrintNodeService
{
    private const API_BASE = 'https://api.printnode.com';

    public function isEnabled(): bool
    {
        $enabled = (bool) Setting::admin('printnode.enabled', false);

        return $enabled && $this->getApiKey() !== '';
    }

    public function getApiKey(): string
    {
        $apiKey = trim((string) Setting::admin('printnode.api_key', ''));

        if ($apiKey !== '') {
            return $apiKey;
        }

        return trim((string) env('PRINTNODE_API_KEY', ''));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPrinters(bool $fresh = false): array
    {
        $cacheKey = 'printnode:printers:' . sha1($this->getApiKey());

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addSeconds(30), function (): array {
            $response = $this->request('get', '/printers');
            $data = $response->json();

            return is_array($data) ? $data : [];
        });
    }

    public function resolvePrinterId(?int $configuredId = null, ?string $configuredName = null): ?int
    {
        if (! empty($configuredId) && $configuredId > 0) {
            return $configuredId;
        }

        $name = trim((string) $configuredName);
        if ($name === '') {
            return null;
        }

        $nameLower = mb_strtolower($name);
        $printers = $this->getPrinters();

        foreach ($printers as $printer) {
            $candidate = mb_strtolower((string) ($printer['name'] ?? ''));
            $description = mb_strtolower((string) ($printer['description'] ?? ''));
            $state = mb_strtolower((string) ($printer['state'] ?? ''));

            if ($state !== '' && $state !== 'online') {
                continue;
            }

            if ($candidate === $nameLower || $description === $nameLower) {
                return (int) ($printer['id'] ?? 0);
            }
        }

        foreach ($printers as $printer) {
            $candidate = mb_strtolower((string) ($printer['name'] ?? ''));
            $description = mb_strtolower((string) ($printer['description'] ?? ''));
            $state = mb_strtolower((string) ($printer['state'] ?? ''));

            if ($state !== '' && $state !== 'online') {
                continue;
            }

            if (str_contains($candidate, $nameLower) || str_contains($description, $nameLower)) {
                return (int) ($printer['id'] ?? 0);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPrinterById(int $printerId): ?array
    {
        foreach ($this->getPrinters() as $printer) {
            if ((int) ($printer['id'] ?? 0) === $printerId) {
                return $printer;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function createRawPrintJob(int $printerId, string $title, string $rawContent, int $qty = 1, array $options = []): array
    {
        $payload = [
            'printerId' => $printerId,
            'title' => $title,
            'contentType' => 'raw_base64',
            'content' => base64_encode($rawContent),
            'source' => 'myadmin-kitchen-duplicate',
            'qty' => max(1, $qty),
        ];

        if ($options !== []) {
            $payload['options'] = $options;
        }

        $response = $this->request('post', '/printjobs', $payload);

        return [
            'printjob_id' => (int) $response->body(),
            'status' => $response->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPdfBase64PrintJob(int $printerId, string $title, string $pdfBinary, int $qty = 1, array $options = []): array
    {
        $payload = [
            'printerId' => $printerId,
            'title' => $title,
            'contentType' => 'pdf_base64',
            'content' => base64_encode($pdfBinary),
            'source' => 'myadmin-kitchen-duplicate',
            'qty' => max(1, $qty),
        ];

        if ($options !== []) {
            $payload['options'] = $options;
        }

        $response = $this->request('post', '/printjobs', $payload);

        return [
            'printjob_id' => (int) $response->body(),
            'status' => $response->status(),
        ];
    }

    private function request(string $method, string $uri, array $payload = []): Response
    {
        $apiKey = $this->getApiKey();

        if ($apiKey === '') {
            throw new \RuntimeException('PrintNode API key is not configured.');
        }

        $url = rtrim(self::API_BASE, '/') . $uri;

        $http = Http::timeout(20)
            ->acceptJson()
            ->withBasicAuth($apiKey, '');

        $response = $method === 'get'
            ? $http->get($url)
            : $http->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'PrintNode request failed [%s] %s',
                $response->status(),
                $response->body()
            ));
        }

        return $response;
    }
}
