<?php

namespace App\Reports\DataProviders;

use App\Models\DeliveryZone;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\Order;
use App\Services\DeliveryZoneResolverService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AlignOrderZonesOperationProvider implements ReportDataProviderInterface
{
    public function __construct(private readonly DeliveryZoneResolverService $zoneResolver) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolve(array $params, array $context = []): array
    {
        $applyChanges = $this->toBool($params['apply_changes'] ?? false);
        $onlyWithoutZone = $this->toBool($params['only_without_zone'] ?? true);
        $useStreetFallback = $this->toBool($params['use_street_fallback'] ?? true);
        $geocodeMissingCoords = $this->toBool($params['geocode_missing_coords'] ?? false);

        $chunkSize = max(50, min(1000, (int) ($params['chunk_size'] ?? 200)));
        $maxOrders = max(0, (int) ($params['max_orders'] ?? 0));

        $dateFrom = trim((string) ($params['date_from'] ?? ''));
        $dateTo = trim((string) ($params['date_to'] ?? ''));
        $brands = trim((string) ($params['brands'] ?? 'all'));

        $zones = DeliveryZone::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'delivery_price']);

        $zonesByPrice = [];
        foreach ($zones as $zone) {
            $priceKey = $this->priceKey((float) ($zone->delivery_price ?? 0));
            if (! isset($zonesByPrice[$priceKey])) {
                $zonesByPrice[$priceKey] = $zone;
            }
        }

        $streetMap = $useStreetFallback ? $this->buildStreetZoneMap() : [];

        $baseQuery = Order::query()
            ->whereNotIn('status', ['cart', 'cancelled'])
            ->where('self_pickup', false)
            ->with(['clientAddress:id,street,house,latitude,longitude,formatted_address']);

        if ($onlyWithoutZone) {
            $baseQuery->whereNull('delivery_zone_id');
        }

        if ($dateFrom !== '' && $dateTo !== '') {
            $baseQuery->whereRaw('DATE(COALESCE(date_order, created_at)) BETWEEN ? AND ?', [$dateFrom, $dateTo]);
        }

        if ($brands !== '' && $brands !== 'all') {
            if ($brands === 'local') {
                $baseQuery->whereNull('source_id');
            } else {
                $baseQuery->where('source_id', (int) $brands);
            }
        }

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'zone_rows' => [],
            'method_rows' => [],
            'unknown_rows' => [],
            'errors' => 0,
            'coords_found' => 0,
            'coords_geocoded' => 0,
        ];

        $stop = false;
        $runAt = now();

        $baseQuery
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use (
                &$stats,
                &$stop,
                $maxOrders,
                $applyChanges,
                $zonesByPrice,
                $streetMap,
                $useStreetFallback,
                $geocodeMissingCoords,
                $runAt,
            ): void {
                foreach ($orders as $order) {
                    if ($stop) {
                        break;
                    }

                    if ($maxOrders > 0 && $stats['processed'] >= $maxOrders) {
                        $stop = true;
                        break;
                    }

                    $stats['processed']++;

                    try {
                        $resolved = $this->resolveOrderZone(
                            $order,
                            $zonesByPrice,
                            $streetMap,
                            $useStreetFallback,
                            $geocodeMissingCoords,
                            $applyChanges,
                            $stats,
                        );

                        $zoneName = $resolved['zone_name'] ?? 'Unknown';
                        $method = $resolved['method'] ?? 'unknown';

                        if (! isset($stats['zone_rows'][$zoneName])) {
                            $stats['zone_rows'][$zoneName] = 0;
                        }
                        $stats['zone_rows'][$zoneName]++;

                        if (! isset($stats['method_rows'][$method])) {
                            $stats['method_rows'][$method] = 0;
                        }
                        $stats['method_rows'][$method]++;

                        if (($resolved['zone_id'] ?? null) === null && count($stats['unknown_rows']) < 20) {
                            $stats['unknown_rows'][] = [
                                'order_id' => (int) $order->id,
                                'order_number' => (string) ($order->number ?? ''),
                                'street' => (string) ($order->clientAddress?->street ?? ''),
                                'house' => (string) ($order->clientAddress?->house ?? ''),
                                'shipping_price' => (float) ($order->shipping_price ?? 0),
                            ];
                        }

                        if ($applyChanges && ($resolved['zone_id'] ?? null) !== null) {
                            $updated = $order->newQuery()
                                ->whereKey($order->id)
                                ->where(function ($query) use ($resolved): void {
                                    $query
                                        ->whereNull('delivery_zone_id')
                                        ->orWhere('delivery_zone_id', '!=', (int) $resolved['zone_id'])
                                        ->orWhereNull('zone_resolution_method')
                                        ->orWhereNull('zone_resolved_at');
                                })
                                ->update([
                                    'delivery_zone_id' => (int) $resolved['zone_id'],
                                    'zone_resolution_method' => (string) ($resolved['method'] ?? 'unknown'),
                                    'zone_resolved_at' => $runAt,
                                    'updated_at' => now(),
                                ]);

                            if ($updated > 0) {
                                $stats['updated'] += $updated;
                            }
                        }
                    } catch (\Throwable) {
                        $stats['errors']++;
                    }
                }
            });

        ksort($stats['zone_rows']);
        ksort($stats['method_rows']);

        $zoneRows = [];
        foreach ($stats['zone_rows'] as $zoneName => $count) {
            $zoneRows[] = [
                'zone_name' => (string) $zoneName,
                'orders_count' => (int) $count,
            ];
        }

        $methodRows = [];
        foreach ($stats['method_rows'] as $method => $count) {
            $methodRows[] = [
                'method' => (string) $method,
                'orders_count' => (int) $count,
            ];
        }

        return [
            'run_mode' => $applyChanges ? 'apply' : 'dry_run',
            'processed' => (int) $stats['processed'],
            'updated' => (int) $stats['updated'],
            'errors' => (int) $stats['errors'],
            'coords_found' => (int) $stats['coords_found'],
            'coords_geocoded' => (int) $stats['coords_geocoded'],
            'zone_rows' => $zoneRows,
            'method_rows' => $methodRows,
            'unknown_rows' => $stats['unknown_rows'],
            'started_at' => $runAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, \App\Models\DeliveryZone> $zonesByPrice
     * @param array<string, int> $streetMap
     * @param array<string, mixed> $stats
     * @return array{zone_id: int|null, zone_name: string, method: string}
     */
    private function resolveOrderZone(
        Order $order,
        array $zonesByPrice,
        array $streetMap,
        bool $useStreetFallback,
        bool $geocodeMissingCoords,
        bool $applyChanges,
        array &$stats,
    ): array {
        $zone = null;
        $method = 'unknown';

        $address = $order->clientAddress;
        $lat = (float) ($address?->latitude ?? 0);
        $lng = (float) ($address?->longitude ?? 0);

        if ($lat !== 0.0 && $lng !== 0.0) {
            $zone = $this->zoneResolver->resolveZoneByCoordinates($lat, $lng);
            if ($zone) {
                $method = 'coords';
                $stats['coords_found']++;
            }
        }

        if (! $zone && $geocodeMissingCoords && $address && ($lat === 0.0 || $lng === 0.0)) {
            $coords = $this->geocodeAddress($address);
            if ($coords !== null) {
                $lat = (float) ($coords['lat'] ?? 0);
                $lng = (float) ($coords['lng'] ?? 0);

                if ($lat !== 0.0 && $lng !== 0.0) {
                    if ($applyChanges) {
                        $address->forceFill([
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'formatted_address' => (string) ($coords['formatted_address'] ?? $address->formatted_address),
                        ])->saveQuietly();
                    }

                    $zone = $this->zoneResolver->resolveZoneByCoordinates($lat, $lng);
                    if ($zone) {
                        $method = 'geocoded_coords';
                        $stats['coords_geocoded']++;
                    }
                }
            }
        }

        if (! $zone) {
            $priceKey = $this->priceKey((float) ($order->shipping_price ?? 0));
            if (isset($zonesByPrice[$priceKey])) {
                $zone = $zonesByPrice[$priceKey];
                $method = 'shipping_price_exact';
            }
        }

        if (! $zone && $useStreetFallback) {
            $streetKey = $this->normalizeStreet((string) ($address?->street ?? ''));
            if ($streetKey !== '' && isset($streetMap[$streetKey])) {
                $zone = DeliveryZone::query()->find((int) $streetMap[$streetKey]);
                if ($zone) {
                    $method = 'street_majority';
                }
            }
        }

        return [
            'zone_id' => $zone?->id,
            'zone_name' => (string) ($zone?->name ?? 'Unknown'),
            'method' => $method,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildStreetZoneMap(): array
    {
        $rows = Order::query()
            ->leftJoin('bs_client_addresses as ca', 'ca.id', '=', 'bs_shop_orders.client_address_id')
            ->whereNotNull('bs_shop_orders.delivery_zone_id')
            ->whereNotNull('ca.street')
            ->where('ca.street', '!=', '')
            ->selectRaw('ca.street as street, bs_shop_orders.delivery_zone_id as zone_id, COUNT(*) as cnt')
            ->groupBy('ca.street', 'bs_shop_orders.delivery_zone_id')
            ->get();

        $agg = [];
        foreach ($rows as $row) {
            $streetKey = $this->normalizeStreet((string) ($row->street ?? ''));
            if ($streetKey === '') {
                continue;
            }

            $zoneId = (int) ($row->zone_id ?? 0);
            $cnt = (int) ($row->cnt ?? 0);
            if ($zoneId <= 0 || $cnt <= 0) {
                continue;
            }

            if (! isset($agg[$streetKey])) {
                $agg[$streetKey] = [
                    'total' => 0,
                    'zones' => [],
                ];
            }

            $agg[$streetKey]['total'] += $cnt;
            if (! isset($agg[$streetKey]['zones'][$zoneId])) {
                $agg[$streetKey]['zones'][$zoneId] = 0;
            }
            $agg[$streetKey]['zones'][$zoneId] += $cnt;
        }

        $map = [];
        foreach ($agg as $streetKey => $data) {
            $total = (int) ($data['total'] ?? 0);
            $zones = (array) ($data['zones'] ?? []);

            if ($total < 3 || $zones === []) {
                continue;
            }

            arsort($zones);
            $zoneId = (int) array_key_first($zones);
            $max = (int) reset($zones);
            $share = $total > 0 ? ($max / $total) : 0.0;

            if ($share >= 0.8) {
                $map[$streetKey] = $zoneId;
            }
        }

        return $map;
    }

    /**
     * @return array{lat: float, lng: float, formatted_address: string|null}|null
     */
    private function geocodeAddress(ClientAddress $address): ?array
    {
        $apiKey = trim((string) config('services.google_maps.key', ''));
        if ($apiKey === '') {
            return null;
        }

        $street = trim((string) ($address->street ?? ''));
        $house = trim((string) ($address->house ?? ''));
        $city = trim((string) ($address->city ?? 'Київ'));

        if ($street === '') {
            return null;
        }

        $query = implode(', ', array_filter([$street, $house, $city, 'Україна']));

        try {
            $response = Http::timeout(8)->acceptJson()->get(
                'https://maps.googleapis.com/maps/api/geocode/json',
                [
                    'address' => $query,
                    'language' => 'uk',
                    'key' => $apiKey,
                ]
            );

            if (! $response->ok()) {
                return null;
            }

            $json = $response->json();
            if (! is_array($json) || (string) ($json['status'] ?? '') !== 'OK') {
                return null;
            }

            $first = $json['results'][0] ?? null;
            if (! is_array($first)) {
                return null;
            }

            $location = $first['geometry']['location'] ?? null;
            if (! is_array($location)) {
                return null;
            }

            $lat = (float) ($location['lat'] ?? 0);
            $lng = (float) ($location['lng'] ?? 0);
            if ($lat === 0.0 || $lng === 0.0) {
                return null;
            }

            return [
                'lat' => $lat,
                'lng' => $lng,
                'formatted_address' => isset($first['formatted_address']) ? (string) $first['formatted_address'] : null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeStreet(string $street): string
    {
        $normalized = mb_strtolower(trim($street));
        if ($normalized === '') {
            return '';
        }

        $normalized = Str::replaceMatches('/\s+/u', ' ', $normalized);
        $normalized = str_replace(['.', ',', '"', "'"], '', $normalized);

        $prefixes = ['вул ', 'вулиця ', 'ул ', 'улица ', 'проспект ', 'пр-т ', 'бульвар '];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $normalized = substr($normalized, strlen($prefix));
                break;
            }
        }

        return trim($normalized);
    }

    private function priceKey(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
