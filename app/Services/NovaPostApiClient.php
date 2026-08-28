<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Shop\Order;
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

    public function senderCounterparties(): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        return Cache::remember('nova_post:sender_counterparties:v1', now()->addHours(6), function () use ($key): array {
            $payload = $this->post($key, 'Counterparty', 'getCounterparties', [
                'CounterpartyProperty' => 'Sender',
                'Page' => '1',
            ], 'sender counterparties');

            return collect(data_get($payload, 'data', []))
                ->map(fn (array $counterparty): array => [
                    'ref' => (string) ($counterparty['Ref'] ?? ''),
                    'label' => trim((string) ($counterparty['Description'] ?? $counterparty['DescriptionRu'] ?? $counterparty['Ref'] ?? '')),
                ])
                ->filter(fn (array $counterparty): bool => $counterparty['ref'] !== '' && $counterparty['label'] !== '')
                ->values()
                ->all();
        });
    }

    public function senderContactPersons(string $senderRef): array
    {
        $senderRef = trim($senderRef);
        if ($senderRef === '') {
            return [];
        }

        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        return Cache::remember('nova_post:sender_contacts:v1:' . $senderRef, now()->addHours(6), function () use ($key, $senderRef): array {
            $payload = $this->post($key, 'Counterparty', 'getCounterpartyContactPersons', [
                'Ref' => $senderRef,
                'Page' => '1',
            ], 'sender contacts');

            return collect(data_get($payload, 'data', []))
                ->map(fn (array $contact): array => [
                    'ref' => (string) ($contact['Ref'] ?? ''),
                    'label' => trim(collect([
                        $contact['LastName'] ?? null,
                        $contact['FirstName'] ?? null,
                        $contact['MiddleName'] ?? null,
                        $contact['Phones'] ?? null,
                    ])->filter(fn ($part): bool => filled($part))->implode(' ')),
                ])
                ->filter(fn (array $contact): bool => $contact['ref'] !== '' && $contact['label'] !== '')
                ->values()
                ->all();
        });
    }

    public function createInternetDocument(Order $order, array $overrides = []): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        $settings = $this->senderSettings();
        $this->validateInternetDocumentData($order, $settings);

        $cost = $this->moneyValue($overrides['cost'] ?? null)
            ?: $this->moneyValue($order->nova_declared_value)
            ?: $this->moneyValue($order->grand_total)
            ?: (float) $settings['default_cost'];
        $codAmount = $this->moneyValue($overrides['cod_amount'] ?? null)
            ?: $this->moneyValue($order->nova_cod_amount);
        $payerType = $this->payerType($overrides['payer_type'] ?? $order->nova_payer, (string) $settings['default_payer_type']);
        $paymentMethod = $this->paymentMethod($payerType, (string) ($overrides['payment_method'] ?? $settings['default_payment_method']));
        $recipient = $this->createRecipientCounterparty($key, $order);
        $serviceType = in_array((string) $order->nova_delivery_type, ['warehouse', 'postomat'], true)
            ? 'WarehouseWarehouse'
            : 'WarehouseDoors';

        $properties = [
            'PayerType' => $payerType,
            'PaymentMethod' => $paymentMethod,
            'DateTime' => now()->format('d.m.Y'),
            'CargoType' => 'Parcel',
            'VolumeGeneral' => $this->volumeFromOverrides($overrides, (string) $settings['default_volume']),
            'Weight' => (string) ($overrides['weight'] ?? $settings['default_weight']),
            'ServiceType' => $serviceType,
            'SeatsAmount' => (string) max(1, (int) ($overrides['seats_amount'] ?? $settings['default_seats_amount'])),
            'Description' => $this->documentDescription($order, (string) ($overrides['description'] ?? $settings['default_description'])),
            'Cost' => (string) max(1, $cost),
            'CitySender' => (string) $settings['sender_city_ref'],
            'Sender' => (string) $settings['sender_ref'],
            'SenderAddress' => (string) $settings['sender_address_ref'],
            'ContactSender' => (string) $settings['sender_contact_ref'],
            'SendersPhone' => $this->normalizePhone((string) $settings['sender_phone']),
            'Recipient' => $recipient['recipient_ref'],
            'CityRecipient' => (string) $order->nova_city_ref,
            'RecipientAddress' => (string) $order->nova_warehouse_ref,
            'ContactRecipient' => $recipient['contact_ref'],
            'RecipientType' => 'PrivatePerson',
            'RecipientName' => $this->recipientName($order),
            'RecipientContactName' => $this->recipientName($order),
            'RecipientsPhone' => $this->normalizePhone((string) $order->recipient_phone),
        ];

        if ($codAmount > 0) {
            $properties['BackwardDeliveryData'] = [[
                'PayerType' => $payerType,
                'CargoType' => 'Money',
                'RedeliveryString' => (string) $codAmount,
            ]];
        }

        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => 'InternetDocument',
                'calledMethod' => 'save',
                'methodProperties' => $properties,
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post TTN creation request failed', [
                'order_id' => $order->getKey(),
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Nova Post TTN request failed with HTTP status ' . $response->status() . '.');
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post TTN creation response was unsuccessful', [
                'order_id' => $order->getKey(),
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            throw new RuntimeException($this->apiMessage($payload, 'Nova Post did not create TTN.'));
        }

        $data = (array) data_get($payload, 'data.0', []);
        $number = trim((string) ($data['IntDocNumber'] ?? ''));

        if ($number === '') {
            throw new RuntimeException('Nova Post created document response does not contain TTN number.');
        }

        return [
            'number' => $number,
            'ref' => trim((string) ($data['Ref'] ?? '')),
            'cost' => $this->moneyValue($data['CostOnSite'] ?? null),
            'estimated_delivery_date' => trim((string) ($data['EstimatedDeliveryDate'] ?? '')),
            'raw' => $data,
        ];
    }

    public function printDocument(string $ttn, string $type = 'pdf'): array
    {
        return $this->downloadPrintForm(
            $this->printUrl('printDocument', $ttn, $type),
            $ttn,
            $type,
            'document'
        );
    }

    public function printMarking100x100(string $ttn, string $type = 'pdf'): array
    {
        return $this->downloadPrintForm(
            $this->printUrl('printMarking100x100', $ttn, $type) . '/zebra',
            $ttn,
            $type,
            'marking-100x100'
        );
    }

    public function cancelInternetDocument(string $ttn): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        $ttn = trim($ttn);
        if ($ttn === '') {
            throw new RuntimeException('Nova Post TTN number is empty.');
        }

        $ref = $this->documentRefByNumber($key, $ttn);

        $payload = $this->post($key, 'InternetDocument', 'delete', [
            'DocumentRefs' => [$ref],
        ], 'TTN cancellation');

        return [
            'ref' => $ref,
            'raw' => data_get($payload, 'data', []),
        ];
    }

    public function trackingStatus(string $ttn, ?string $phone = null): array
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        $ttn = trim($ttn);
        if ($ttn === '') {
            throw new RuntimeException('Nova Post TTN number is empty.');
        }

        $document = [
            'DocumentNumber' => $ttn,
        ];

        $phone = $this->normalizePhone((string) $phone);
        if ($phone !== '') {
            $document['Phone'] = $phone;
        }

        $payload = $this->post($key, 'TrackingDocument', 'getStatusDocuments', [
            'Documents' => [$document],
        ], 'TTN tracking status');

        $data = (array) data_get($payload, 'data.0', []);
        $statusCode = trim((string) ($data['StatusCode'] ?? $data['StateId'] ?? ''));
        $statusText = trim((string) ($data['Status'] ?? ''));

        return [
            'status' => $this->normalizeTrackingStatus($statusCode, $statusText),
            'status_code' => $statusCode,
            'status_text' => $statusText,
            'scheduled_delivery_date' => trim((string) ($data['ScheduledDeliveryDate'] ?? $data['EstimatedDeliveryDate'] ?? $data['DateExpectedDelivery'] ?? '')),
            'actual_delivery_date' => trim((string) ($data['ActualDeliveryDate'] ?? $data['DateDelivered'] ?? '')),
            'status_date' => trim((string) ($data['DateScan'] ?? $data['DateModified'] ?? '')),
            'raw' => $data,
        ];
    }

    public function updateOrderTrackingStatus(Order $order): array
    {
        $ttn = trim((string) $order->nova_ttn);
        $oldStatus = (string) $order->nova_status;
        $tracking = $this->trackingStatus($ttn, (string) $order->recipient_phone);

        $order->forceFill([
            'nova_status' => $tracking['status'],
        ])->save();

        return [
            ...$tracking,
            'old_status' => $oldStatus,
            'new_status' => $tracking['status'],
        ];
    }

    private function downloadPrintForm(string $url, string $ttn, string $type, string $kind): array
    {
        $response = Http::timeout(20)
            ->accept($type === 'pdf' ? 'application/pdf' : 'text/html')
            ->get($url);

        if (! $response->successful()) {
            Log::warning('Nova Post TTN print form request failed', [
                'ttn' => $ttn,
                'kind' => $kind,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Nova Post print request failed with HTTP status ' . $response->status() . '.');
        }

        return [
            'body' => $response->body(),
            'content_type' => $type === 'pdf' ? 'application/pdf' : 'text/html; charset=utf-8',
            'filename' => 'nova-post-' . $kind . '-' . preg_replace('/\D+/', '', $ttn) . '.' . $type,
        ];
    }

    private function documentRefByNumber(string $key, string $ttn): string
    {
        $payload = $this->post($key, 'InternetDocument', 'getDocumentList', [
            'IntDocNumber' => $ttn,
            'Page' => '1',
        ], 'TTN lookup');

        $ref = trim((string) data_get($payload, 'data.0.Ref', ''));

        if ($ref === '') {
            throw new RuntimeException('Nova Post TTN Ref was not found for number ' . $ttn . '.');
        }

        return $ref;
    }

    private function normalizeTrackingStatus(string $statusCode, string $statusText): string
    {
        $text = mb_strtolower($statusText);

        if (str_contains($text, 'ще не надав')
            || str_contains($text, 'ще не передано')
            || str_contains($text, 'не надано')
            || str_contains($text, 'не передано')
            || str_contains($text, 'самостійно створив')
            || str_contains($text, 'самостоятельно создал')) {
            return 'created';
        }

        if (in_array($statusCode, ['2', '102', '103', '106'], true)
            || str_contains($text, 'скас')
            || str_contains($text, 'видал')) {
            return 'cancelled';
        }

        if (in_array($statusCode, ['9', '10', '11'], true)
            || str_contains($text, 'отримано')
            || str_contains($text, 'получено')) {
            return 'received';
        }

        if (in_array($statusCode, ['7', '8'], true)
            || str_contains($text, 'прибув')
            || str_contains($text, 'прибыла')
            || str_contains($text, 'відділен')) {
            return 'arrived';
        }

        if ($statusCode !== '' || $statusText !== '') {
            return 'in_transit';
        }

        return 'created';
    }

    private function printUrl(string $method, string $ttn, string $type): string
    {
        $key = $this->apiKey();
        if ($key === '') {
            throw new RuntimeException('Nova Post API key is not configured.');
        }

        $ttn = trim($ttn);
        if ($ttn === '') {
            throw new RuntimeException('Nova Post TTN number is empty.');
        }

        $type = in_array($type, ['pdf', 'html'], true) ? $type : 'pdf';

        return sprintf(
            'https://my.novaposhta.ua/orders/%s/orders[]/%s/type/%s/apiKey/%s',
            $method,
            rawurlencode($ttn),
            $type,
            rawurlencode($key)
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

    private function post(string $key, string $model, string $method, array $properties, string $context): array
    {
        $response = Http::timeout((int) config('services.nova_post.timeout', 8))
            ->acceptJson()
            ->post((string) config('services.nova_post.base_url'), [
                'apiKey' => $key,
                'modelName' => $model,
                'calledMethod' => $method,
                'methodProperties' => $properties,
            ]);

        if (! $response->successful()) {
            Log::warning('Nova Post request failed', [
                'context' => $context,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('Nova Post ' . $context . ' request failed with HTTP status ' . $response->status() . '.');
        }

        $payload = $response->json();
        if (! (bool) data_get($payload, 'success')) {
            Log::warning('Nova Post response was unsuccessful', [
                'context' => $context,
                'errors' => data_get($payload, 'errors', []),
                'warnings' => data_get($payload, 'warnings', []),
            ]);

            throw new RuntimeException($this->apiMessage($payload, 'Nova Post ' . $context . ' request failed.'));
        }

        return is_array($payload) ? $payload : [];
    }

    private function createRecipientCounterparty(string $key, Order $order): array
    {
        $name = $this->recipientNameParts($order);
        $cacheKey = 'nova_post:recipient_counterparty:v1:' . md5(implode('|', [
            (string) $order->nova_city_ref,
            $this->normalizePhone((string) $order->recipient_phone),
            $name['first_name'],
            $name['middle_name'],
            $name['last_name'],
        ]));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($key, $order, $name): array {
            $payload = $this->post($key, 'Counterparty', 'save', [
                'CounterpartyProperty' => 'Recipient',
                'CounterpartyType' => 'PrivatePerson',
                'FirstName' => $name['first_name'],
                'MiddleName' => $name['middle_name'],
                'LastName' => $name['last_name'],
                'Phone' => $this->normalizePhone((string) $order->recipient_phone),
                'CityRef' => (string) $order->nova_city_ref,
            ], 'recipient counterparty creation');

            $data = (array) data_get($payload, 'data.0', []);
            $recipientRef = trim((string) ($data['Ref'] ?? ''));
            $contactRef = trim((string) (
                data_get($data, 'ContactPerson.data.0.Ref')
                ?: data_get($data, 'ContactPerson.Ref')
                ?: data_get($data, 'ContactPerson.0.Ref')
                ?: ''
            ));

            if ($recipientRef === '' || $contactRef === '') {
                Log::warning('Nova Post recipient creation response did not contain refs', [
                    'order_id' => $order->getKey(),
                    'response' => $data,
                ]);

                throw new RuntimeException('Nova Post recipient was created without Recipient/ContactRecipient refs.');
            }

            return [
                'recipient_ref' => $recipientRef,
                'contact_ref' => $contactRef,
            ];
        });
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

    private function validateInternetDocumentData(Order $order, array $settings): void
    {
        $missing = [];

        foreach ([
            'sender_ref' => 'Sender Ref',
            'sender_contact_ref' => 'Contact Sender Ref',
            'sender_city_ref' => 'City Sender Ref',
            'sender_address_ref' => 'Sender Address Ref',
            'sender_phone' => 'Sender phone',
        ] as $key => $label) {
            if (blank($settings[$key] ?? null)) {
                $missing[] = $label;
            }
        }

        foreach ([
            'sender_ref' => 'Sender Ref',
            'sender_contact_ref' => 'Contact Sender Ref',
            'sender_city_ref' => 'City Sender Ref',
            'sender_address_ref' => 'Sender Address Ref',
        ] as $key => $label) {
            if (filled($settings[$key] ?? null) && ! Str::isUuid((string) $settings[$key])) {
                $missing[] = $label . ' must be Ref UUID';
            }
        }

        if (! in_array((string) $order->nova_delivery_type, ['warehouse', 'postomat'], true)) {
            $missing[] = 'Nova Post warehouse/postomat delivery type';
        }

        if (blank($order->nova_city_ref)) {
            $missing[] = 'Recipient city Ref';
        } elseif (! Str::isUuid((string) $order->nova_city_ref)) {
            $missing[] = 'Recipient city Ref must be UUID';
        }

        if (blank($order->nova_warehouse_ref)) {
            $missing[] = 'Recipient warehouse Ref';
        } elseif (! Str::isUuid((string) $order->nova_warehouse_ref)) {
            $missing[] = 'Recipient warehouse Ref must be UUID';
        }

        if ($this->recipientName($order) === '') {
            $missing[] = 'Recipient name';
        }

        if ($this->normalizePhone((string) $order->recipient_phone) === '') {
            $missing[] = 'Recipient phone';
        }

        if ($missing !== []) {
            throw new RuntimeException('Cannot create Nova Post TTN. Missing: ' . implode(', ', $missing) . '.');
        }
    }

    private function recipientName(Order $order): string
    {
        $name = trim(collect([
            $order->recipient_surname,
            $order->recipient_name,
            $order->recipient_patronymic,
        ])->filter(fn ($part): bool => filled($part))->implode(' '));

        if ($name !== '') {
            return $name;
        }

        return trim((string) $order->clients?->full_name);
    }

    private function recipientNameParts(Order $order): array
    {
        $surname = trim((string) $order->recipient_surname);
        $firstName = trim((string) $order->recipient_name);
        $middleName = trim((string) $order->recipient_patronymic);

        if ($firstName === '' && $surname === '') {
            $parts = preg_split('/\s+/u', trim((string) $order->clients?->full_name)) ?: [];
            $surname = (string) ($parts[0] ?? '');
            $firstName = (string) ($parts[1] ?? '');
            $middleName = (string) ($parts[2] ?? '');
        }

        if ($firstName === '' && $surname !== '') {
            $firstName = $surname;
        }

        if ($surname === '' && $firstName !== '') {
            $surname = $firstName;
        }

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $surname,
        ];
    }

    private function documentDescription(Order $order, string $default): string
    {
        $number = trim((string) ($order->number ?: $order->getKey()));
        $description = trim($default !== '' ? $default : 'Parcel');

        return $number !== '' ? $description . ', order ' . $number : $description;
    }

    private function payerType(mixed $orderPayer, string $default): string
    {
        return match (mb_strtolower(trim((string) $orderPayer))) {
            'sender' => 'Sender',
            'recipient' => 'Recipient',
            default => in_array($default, ['Sender', 'Recipient'], true) ? $default : 'Recipient',
        };
    }

    private function paymentMethod(string $payerType, string $default): string
    {
        $method = in_array($default, ['Cash', 'NonCash'], true) ? $default : 'Cash';

        return $payerType === 'Sender' && $method === 'NonCash' ? 'Cash' : $method;
    }

    private function volumeFromOverrides(array $overrides, string $default): string
    {
        $length = (float) ($overrides['length_cm'] ?? 0);
        $width = (float) ($overrides['width_cm'] ?? 0);
        $height = (float) ($overrides['height_cm'] ?? 0);

        if ($length <= 0 || $width <= 0 || $height <= 0) {
            return $default;
        }

        $volume = $length * $width * $height / 1000000;

        return rtrim(rtrim(number_format($volume, 6, '.', ''), '0'), '.');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '38' . $digits;
        }

        return strlen($digits) === 12 && str_starts_with($digits, '380') ? $digits : '';
    }

    private function moneyValue(mixed $value): float
    {
        $normalized = str_replace(',', '.', (string) $value);

        return is_numeric($normalized) ? max(0.0, (float) $normalized) : 0.0;
    }

    private function apiMessage(array $payload, string $fallback): string
    {
        $messages = collect([
            ...((array) data_get($payload, 'errors', [])),
            ...((array) data_get($payload, 'warnings', [])),
            ...((array) data_get($payload, 'info', [])),
        ])
            ->filter(fn ($message): bool => filled($message))
            ->map(fn ($message): string => (string) $message)
            ->values()
            ->all();

        return $messages !== [] ? implode(' ', $messages) : $fallback;
    }
}
