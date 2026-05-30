<?php

namespace App\Services;

use App\Models\DeliveryZone;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\Order;

class OrderZoneSyncService
{
    public function __construct(private readonly DeliveryZoneResolverService $zoneResolver) {}

    public function syncIfNeeded(Order $order, bool $force = false): void
    {
        $order->loadMissing('clientAddress');
        $address = $order->clientAddress;

        if ((bool) $order->self_pickup) {
            $this->persistOrderZone($order, null, 'pickup', $force);
            return;
        }

        if (! $force && (int) ($order->delivery_zone_id ?? 0) > 0) {
            $this->syncAddressFromOrder($order, $address);
            return;
        }

        $zone = null;
        $method = 'unknown';

        if (! $force && $address && (int) ($address->delivery_zone_id ?? 0) > 0) {
            $zone = DeliveryZone::query()->find((int) $address->delivery_zone_id);
            if ($zone) {
                $method = 'address_cached';
            }
        }

        if (! $zone) {
            $coords = $this->extractCoordinates($order, $address);
            if ($coords !== null) {
                $zone = $this->zoneResolver->resolveZoneByCoordinates($coords['lat'], $coords['lng']);
                if ($zone) {
                    $method = 'coords';
                }
            }
        }

        if (! $zone) {
            $zone = $this->resolveByExactShippingPrice((float) ($order->shipping_price ?? 0));
            if ($zone) {
                $method = 'shipping_price_exact';
            }
        }

        if (! $zone) {
            $this->persistOrderZone($order, null, 'unknown', true);
            if ($address) {
                $this->persistAddressZone($address, null, 'unknown', true);
            }
            return;
        }

        $this->persistOrderZone($order, (int) $zone->id, $method, true);
        if ($address) {
            $this->persistAddressZone($address, (int) $zone->id, $method, true);
        }
    }

    public function resetOrderZone(Order $order): void
    {
        $this->persistOrderZone($order, null, null, true);
    }

    private function syncAddressFromOrder(Order $order, ?ClientAddress $address): void
    {
        if (! $address) {
            return;
        }

        if ((int) ($address->delivery_zone_id ?? 0) > 0) {
            return;
        }

        $this->persistAddressZone(
            $address,
            (int) ($order->delivery_zone_id ?? 0) ?: null,
            (string) ($order->zone_resolution_method ?? 'order_cached'),
            true,
        );
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function extractCoordinates(Order $order, ?ClientAddress $address): ?array
    {
        $lat = (float) ($address?->latitude ?? 0);
        $lng = (float) ($address?->longitude ?? 0);

        if ($lat !== 0.0 && $lng !== 0.0) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        return null;
    }

    private function resolveByExactShippingPrice(float $shippingPrice): ?DeliveryZone
    {
        return DeliveryZone::query()
            ->where('is_active', true)
            ->whereRaw('ROUND(delivery_price, 2) = ?', [round($shippingPrice, 2)])
            ->orderBy('sort_order')
            ->first();
    }

    private function persistOrderZone(Order $order, ?int $zoneId, ?string $method, bool $forceWrite): void
    {
        $currentZoneId = $order->delivery_zone_id !== null ? (int) $order->delivery_zone_id : null;
        $currentMethod = $order->zone_resolution_method !== null ? (string) $order->zone_resolution_method : null;

        if (! $forceWrite && $currentZoneId === $zoneId && $currentMethod === $method) {
            return;
        }

        $order->forceFill([
            'delivery_zone_id' => $zoneId,
            'zone_resolution_method' => $method,
            'zone_resolved_at' => $method !== null ? now() : null,
        ])->saveQuietly();
    }

    private function persistAddressZone(ClientAddress $address, ?int $zoneId, ?string $method, bool $forceWrite): void
    {
        $currentZoneId = $address->delivery_zone_id !== null ? (int) $address->delivery_zone_id : null;
        $currentMethod = $address->zone_resolution_method !== null ? (string) $address->zone_resolution_method : null;

        if (! $forceWrite && $currentZoneId === $zoneId && $currentMethod === $method) {
            return;
        }

        $address->forceFill([
            'delivery_zone_id' => $zoneId,
            'zone_resolution_method' => $method,
            'zone_resolved_at' => $method !== null ? now() : null,
        ])->saveQuietly();
    }
}
