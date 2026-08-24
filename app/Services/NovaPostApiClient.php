<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NovaPostApiClient
{
    private const POSTOMAT_TYPE_REFS = [
        'f9316480-5f2d-425d-bc2c-ac7cd29decf0',
        '95dc212d-479c-4ffb-a8ab-8c1b9073d0bc',
    ];

    public function searchCities(string $query, int $limit = 20): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $limit = max(1, min($limit, 30));

        if ($query === '') {
            return [];
        }

        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        return Cache::remember(
            'nova_post:cities:v5:' . md5(mb_strtolower($query) . ':' . $limit),
            now()->addHours(6),
            fn () => $this->fetchCities($key, $query, $limit)
        );
    }

    public function searchWarehouses(string $cityRef, string $query = '', int $limit = 30, string $type = 'warehouse'): array
    {
        $cityRef = trim($cityRef);
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $limit = max(1, min($limit, 50));
        $type = $type === 'postomat' ? 'postomat' : 'warehouse';

        if ($cityRef === '') {
            return [];
        }

        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        $cacheKey = 'nova_post:warehouses:v2:' . md5($cityRef . ':' . mb_strtolower($query) . ':' . $limit . ':' . $type);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($key, $cityRef, $query, $limit, $type) {
            if ($type === 'postomat') {
                return collect(self::POSTOMAT_TYPE_REFS)
                    ->flatMap(fn (string $typeRef): array => $this->fetchWarehouses($key, $cityRef, $query, $limit, $typeRef))
                    ->unique('ref')
                    ->sortBy(fn (array $warehouse) => (int) preg_replace('/\D+/', '', $warehouse['number'] ?: '999999'))
                    ->values()
                    ->take($limit)
                    ->all();
            }

            return collect($this->fetchWarehouses($key, $cityRef, $query, $limit))
                ->reject(fn (array $warehouse): bool => in_array($warehouse['type_ref'], self::POSTOMAT_TYPE_REFS, true))
                ->values()
                ->take($limit)
                ->all();
        });
    }

    public function searchStreets(string $cityRef, string $query = '', int $limit = 20): array
    {
        $cityRef = trim($cityRef);
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $limit = max(1, min($limit, 50));

        if ($cityRef === '' || $query === '') {
            return [];
        }

        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        return Cache::remember(
            'nova_post:streets:v1:' . md5($cityRef . ':' . mb_strtolower($query) . ':' . $limit),
            now()->addHours(6),
            fn () => $this->fetchStreets($key, $cityRef, $query, $limit)
        );
    }

    public function deliveryPrices(string $recipientCityRef): array
    {
        $recipientCityRef = trim($recipientCityRef);

        if ($recipientCityRef === '') {
            return [];
        }

        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        return Cache::remember(
            'nova_post:delivery_prices:v1:' . md5($recipientCityRef),
            now()->addHours(6),
            function () use ($key, $recipientCityRef) {
                $warehouse = $this->fetchDocumentPrice($key, $recipientCityRef, 'WarehouseWarehouse');
                $courier = $this->fetchDocumentPrice($key, $recipientCityRef, 'WarehouseDoors');

                return [
                    'nova_branch' => $warehouse,
                    'nova_postomat' => $warehouse,
                    'nova_courier' => $courier,
                ];
            }
        );
    }

    private function fetchCities(string $key, string $query, int $limit): array
    {
        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => 'Address',
                'calledMethod' => 'searchSettlements',
                'methodProperties' => [
                    'CityName' => $query,
                    'Limit' => (string) $limit,
                    'Page' => '1',
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post cities request failed', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return [];
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post cities response was unsuccessful', [
                'query' => $query,
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            return [];
        }

        $normalizedQuery = mb_strtolower($query);

        return collect(data_get($payload, 'data.0.Addresses', []))
            ->map(function (array $city) {
                $description = trim((string) ($city['MainDescription'] ?? ''));
                $area = trim((string) ($city['Area'] ?? ''));
                $region = trim((string) ($city['Region'] ?? ''));
                $areaCode = trim((string) ($city['ParentRegionCode'] ?? 'обл.'));
                $regionCode = trim((string) ($city['RegionTypesCode'] ?? 'р-н'));
                $details = collect([
                    $area !== '' ? trim($area . ' ' . $areaCode) : '',
                    $region !== '' ? trim($region . ' ' . $regionCode) : '',
                ])->filter()->implode(', ');

                $type = trim((string) ($city['SettlementTypeCode'] ?? ''));
                $displayName = trim($type . ' ' . $description);

                return [
                    'ref' => (string) ($city['DeliveryCity'] ?? ''),
                    'settlement_ref' => (string) ($city['Ref'] ?? ''),
                    'name' => $description,
                    'display_name' => $displayName !== '' ? $displayName : $description,
                    'area' => $area,
                    'region' => $region,
                    'details' => $details,
                    'type' => $type,
                    'warehouses' => (int) ($city['Warehouses'] ?? 0),
                    'label' => $details !== '' ? ($displayName !== '' ? $displayName : $description) . ' - ' . $details . '.' : ($displayName !== '' ? $displayName : $description),
                ];
            })
            ->filter(fn (array $city) => $city['ref'] !== '' && $city['name'] !== '')
            ->sort(function (array $left, array $right) use ($normalizedQuery) {
                $typePriority = fn (array $city) => match ($city['type']) {
                    'м.' => 0,
                    'смт' => 1,
                    'с-ще' => 2,
                    'с.' => 3,
                    default => 4,
                };

                $leftName = mb_strtolower($left['name']);
                $rightName = mb_strtolower($right['name']);
                $leftScore = [
                    Str::startsWith($leftName, $normalizedQuery) ? 0 : 1,
                    $leftName === $normalizedQuery ? 0 : 1,
                    $typePriority($left),
                    -1 * (int) $left['warehouses'],
                    mb_strlen($left['name']),
                    $left['name'],
                ];
                $rightScore = [
                    Str::startsWith($rightName, $normalizedQuery) ? 0 : 1,
                    $rightName === $normalizedQuery ? 0 : 1,
                    $typePriority($right),
                    -1 * (int) $right['warehouses'],
                    mb_strlen($right['name']),
                    $right['name'],
                ];

                return $leftScore <=> $rightScore;
            })
            ->values()
            ->take($limit)
            ->all();
    }

    private function fetchWarehouses(string $key, string $cityRef, string $query, int $limit, ?string $typeRef = null): array
    {
        $methodProperties = [
            'CityRef' => $cityRef,
            'FindByString' => $query,
            'Limit' => (string) $limit,
            'Page' => '1',
        ];

        if ($typeRef !== null) {
            $methodProperties['TypeOfWarehouseRef'] = $typeRef;
        }

        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => 'Address',
                'calledMethod' => 'getWarehouses',
                'methodProperties' => $methodProperties,
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post warehouses request failed', [
                'status' => $response->status(),
                'city_ref' => $cityRef,
                'query' => $query,
                'type_ref' => $typeRef,
            ]);

            return [];
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post warehouses response was unsuccessful', [
                'city_ref' => $cityRef,
                'query' => $query,
                'type_ref' => $typeRef,
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            return [];
        }

        return collect(data_get($payload, 'data', []))
            ->map(function (array $warehouse) {
                $description = trim((string) ($warehouse['Description'] ?? ''));

                return [
                    'ref' => (string) ($warehouse['Ref'] ?? ''),
                    'number' => (string) ($warehouse['Number'] ?? ''),
                    'type_ref' => (string) ($warehouse['TypeOfWarehouse'] ?? ''),
                    'name' => $description,
                    'label' => $description,
                    'short_address' => trim((string) ($warehouse['ShortAddress'] ?? '')),
                    'schedule' => [
                        'weekday' => trim((string) data_get($warehouse, 'Schedule.Monday', '')),
                        'saturday' => trim((string) data_get($warehouse, 'Schedule.Saturday', '')),
                        'sunday' => trim((string) data_get($warehouse, 'Schedule.Sunday', '')),
                    ],
                ];
            })
            ->filter(fn (array $warehouse) => $warehouse['ref'] !== '' && $warehouse['name'] !== '')
            ->sortBy(fn (array $warehouse) => (int) preg_replace('/\D+/', '', $warehouse['number'] ?: '999999'))
            ->values()
            ->take($limit)
            ->all();
    }

    private function fetchStreets(string $key, string $cityRef, string $query, int $limit): array
    {
        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => 'Address',
                'calledMethod' => 'getStreet',
                'methodProperties' => [
                    'CityRef' => $cityRef,
                    'FindByString' => $query,
                    'Limit' => (string) $limit,
                    'Page' => '1',
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post streets request failed', [
                'status' => $response->status(),
                'city_ref' => $cityRef,
                'query' => $query,
            ]);

            return [];
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post streets response was unsuccessful', [
                'city_ref' => $cityRef,
                'query' => $query,
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            return [];
        }

        return collect(data_get($payload, 'data', []))
            ->map(function (array $street) {
                $description = trim((string) ($street['Description'] ?? ''));
                $type = trim((string) ($street['StreetsType'] ?? $street['SettlementStreetDescription'] ?? ''));
                $name = trim($type . ' ' . $description);

                return [
                    'ref' => (string) ($street['Ref'] ?? ''),
                    'name' => $description,
                    'type' => $type,
                    'label' => $name !== '' ? $name : $description,
                ];
            })
            ->filter(fn (array $street) => $street['ref'] !== '' && $street['name'] !== '')
            ->sortBy('label')
            ->values()
            ->take($limit)
            ->all();
    }

    private function fetchDocumentPrice(string $key, string $recipientCityRef, string $serviceType): ?float
    {
        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => 'InternetDocument',
                'calledMethod' => 'getDocumentPrice',
                'methodProperties' => [
                    'CitySender' => $this->senderCityRef(),
                    'CityRecipient' => $recipientCityRef,
                    'Weight' => $this->defaultWeight(),
                    'ServiceType' => $serviceType,
                    'Cost' => $this->defaultCost(),
                    'CargoType' => 'Parcel',
                    'SeatsAmount' => '1',
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post price request failed', [
                'status' => $response->status(),
                'recipient_city_ref' => $recipientCityRef,
                'service_type' => $serviceType,
            ]);

            return null;
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post price response was unsuccessful', [
                'recipient_city_ref' => $recipientCityRef,
                'service_type' => $serviceType,
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            return null;
        }

        $cost = data_get($payload, 'data.0.Cost');

        return is_numeric($cost) ? (float) $cost : null;
    }

    private function apiKey(): string
    {
        return trim((string) (Setting::admin('nova_post.key') ?: config('services.nova_post.key')));
    }

    public function senderSettings(): array
    {
        return [
            'sender_ref' => trim((string) (Setting::admin('nova_post.sender_ref') ?: config('services.nova_post.sender_ref'))),
            'sender_contact_ref' => trim((string) (Setting::admin('nova_post.sender_contact_ref') ?: config('services.nova_post.sender_contact_ref'))),
            'sender_city_ref' => $this->senderCityRef(),
            'sender_address_ref' => trim((string) (Setting::admin('nova_post.sender_address_ref') ?: config('services.nova_post.sender_address_ref'))),
            'sender_phone' => trim((string) (Setting::admin('nova_post.sender_phone') ?: config('services.nova_post.sender_phone'))),
            'default_weight' => $this->defaultWeight(),
            'default_length_cm' => $this->defaultDimensionCm('length', 20),
            'default_width_cm' => $this->defaultDimensionCm('width', 15),
            'default_height_cm' => $this->defaultDimensionCm('height', 10),
            'default_volume' => $this->defaultVolume(),
            'default_seats_amount' => (string) max(1, (int) (Setting::admin('nova_post.default_seats_amount') ?: 1)),
            'default_description' => trim((string) (Setting::admin('nova_post.default_description') ?: 'Парфуми')),
            'default_payer_type' => trim((string) (Setting::admin('nova_post.default_payer_type') ?: 'Recipient')),
            'default_payment_method' => trim((string) (Setting::admin('nova_post.default_payment_method') ?: 'Cash')),
            'default_cost' => $this->defaultCost(),
        ];
    }

    private function senderCityRef(): string
    {
        return trim((string) (Setting::admin('nova_post.sender_city_ref') ?: config('services.nova_post.sender_city_ref')));
    }

    private function defaultWeight(): string
    {
        return trim((string) (Setting::admin('nova_post.default_weight') ?: config('services.nova_post.price_weight', '0.5')));
    }

    private function defaultCost(): string
    {
        return trim((string) (Setting::admin('nova_post.default_cost') ?: config('services.nova_post.price_cost', '500')));
    }

    private function defaultDimensionCm(string $dimension, float $default): float
    {
        $value = (float) Setting::admin("nova_post.default_{$dimension}_cm", $default);

        return max(0.1, $value);
    }

    private function defaultVolume(): string
    {
        $legacyVolume = trim((string) Setting::admin('nova_post.default_volume', ''));

        if ($legacyVolume !== '') {
            return $legacyVolume;
        }

        $volume = $this->defaultDimensionCm('length', 20)
            * $this->defaultDimensionCm('width', 15)
            * $this->defaultDimensionCm('height', 10)
            / 1000000;

        return rtrim(rtrim(number_format($volume, 6, '.', ''), '0'), '.');
    }
}
