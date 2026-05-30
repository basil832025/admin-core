<?php

namespace App\Services;

use App\Models\DeliveryZone;
use App\Models\Shop\Order;
use Illuminate\Support\Facades\Log;

class DeliveryCalculationService
{
    /**
     * Рассчитывает стоимость доставки для заказа на основе адреса
     * 
     * @param Order $order
     * @param float|null $orderTotal Фактически оплачиваемая сумма товаров без доставки
     * @return array ['price' => float, 'zone' => DeliveryZone|null, 'is_free' => bool]
     */
    public function calculateDelivery(Order $order, ?float $orderTotal = null): array
    {
        // Если самовывоз, доставка не нужна
        if ($order->self_pickup) {
            return [
                'price' => 0,
                'zone' => null,
                'is_free' => true,
            ];
        }

        // Получаем адрес заказа
        $order->loadMissing('clientAddress');

        $latitude = $order->clientAddress?->latitude;
        $longitude = $order->clientAddress?->longitude;

        if (!$latitude || !$longitude) {
            return [
                'price' => 0,
                'zone' => null,
                'is_free' => false,
            ];
        }

        // Определяем зону доставки по координатам
        // Используем DeliveryZoneResolverService для точного определения по полигонам
        $zoneResolver = app(\App\Services\DeliveryZoneResolverService::class);
        $zone = $zoneResolver->resolveZoneByCoordinates($latitude, $longitude);

        if (!$zone) {
            return [
                'price' => 0,
                'zone' => null,
                'is_free' => false,
            ];
        }

        // Получаем фактически оплачиваемую сумму товаров для проверки бесплатной доставки
        if ($orderTotal === null) {
            $orderTotal = $order->resolveDeliveryBaseAmount();
        }

        // Проверяем, попадает ли заказ под бесплатную доставку
        $isFree = $orderTotal >= (float)$zone->free_delivery_from;

        return [
            'price' => $isFree ? 0 : (float)$zone->delivery_price,
            'zone' => $zone,
            'is_free' => $isFree,
        ];
    }

    /**
     * Определяет зону доставки по координатам
     * Использует ту же логику, что и на странице /delivery (map-cart.js)
     * 
     * @param float|null $latitude
     * @param float|null $longitude
     * @return DeliveryZone|null
     */
    protected function resolveZoneByCoordinates(?float $latitude, ?float $longitude): ?DeliveryZone
    {
        if (!$latitude || !$longitude) {
            return null;
        }

        // Проверяем, что координаты находятся в разумных пределах Киева
        // Киев примерно: lat 50.2-50.6, lng 30.2-30.8
        if ($latitude < 50.0 || $latitude > 50.7 || $longitude < 29.5 || $longitude > 31.0) {
            Log::warning('Coordinates outside Kyiv area', [
                'lat' => $latitude,
                'lng' => $longitude,
            ]);
            return null;
        }

        try {
            // Используем ту же логику, что и в map-cart.js:
            // Сначала пробуем определить зону по полигонам из deliveryAreas
            // Если не получилось, используем расстояние от центра (fallback)
            
            // Пробуем определить зону по полигонам (если они сохранены в БД)
            $zoneByPolygon = $this->resolveZoneByPolygon($latitude, $longitude);
            if ($zoneByPolygon) {
                Log::info('Delivery zone resolved by polygon', [
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'zone' => $zoneByPolygon->name,
                ]);
                return $zoneByPolygon;
            }
            
            // Fallback: используем расстояние от центра (как в map-cart.js)
            // Центр из map-cart.js: const CENTER = { lat: 50.4590851, lng: 30.4182548 }
            $centerLat = 50.4590851;
            $centerLng = 30.4182548;
            
            $distance = $this->calculateDistance($latitude, $longitude, $centerLat, $centerLng);
            
            Log::info('Delivery zone resolved by distance', [
                'lat' => $latitude,
                'lng' => $longitude,
                'distance_km' => round($distance, 2),
            ]);
            
            // Определяем зону по расстоянию (примерные значения, основанные на зонах из map-cart.js)
            // Пороги скорректированы на основе реального распределения зон
            // Green: до 3 км (ближайшие районы центра)
            // Blue: 3-8 км (средние районы)
            // Red: 8-12 км (дальние районы)
            // Brown: более 12 км (самые дальние районы)
            
            if ($distance <= 3) {
                $zone = DeliveryZone::where('name', 'Green')->where('is_active', true)->first();
            } elseif ($distance <= 8) {
                $zone = DeliveryZone::where('name', 'Blue')->where('is_active', true)->first();
            } elseif ($distance <= 12) {
                $zone = DeliveryZone::where('name', 'Red')->where('is_active', true)->first();
            } else {
                $zone = DeliveryZone::where('name', 'Brown')->where('is_active', true)->first();
            }
            
            if ($zone) {
                Log::info('Delivery zone selected by distance', [
                    'zone' => $zone->name,
                    'distance_km' => round($distance, 2),
                ]);
            }
            
            return $zone;
            
        } catch (\Exception $e) {
            Log::error('Error resolving delivery zone by coordinates', [
                'lat' => $latitude,
                'lng' => $longitude,
                'error' => $e->getMessage(),
            ]);
            return $this->getDefaultZone();
        }
    }

    /**
     * Вычисляет расстояние между двумя точками в километрах (формула гаверсинуса)
     */
    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Радиус Земли в километрах

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Определяет зону доставки по полигонам (если они сохранены в БД)
     * Использует алгоритм "point in polygon" для проверки точки внутри полигона
     * 
     * @param float $latitude
     * @param float $longitude
     * @return DeliveryZone|null
     */
    protected function resolveZoneByPolygon(float $latitude, float $longitude): ?DeliveryZone
    {
        // Получаем все активные зоны
        $zones = DeliveryZone::where('is_active', true)->get();
        
        foreach ($zones as $zone) {
            // Проверяем, есть ли полигоны у зоны
            // Предполагаем, что полигоны хранятся в поле polygons как JSON
            // Формат: [[{lat:..., lng:...}, ...], [...]]
            if (!isset($zone->polygons) || empty($zone->polygons)) {
                continue;
            }
            
            $polygons = is_string($zone->polygons) 
                ? json_decode($zone->polygons, true) 
                : $zone->polygons;
            
            if (!is_array($polygons)) {
                continue;
            }
            
            // Проверяем точку внутри каждого полигона зоны
            foreach ($polygons as $polygon) {
                if (!is_array($polygon) || empty($polygon)) {
                    continue;
                }
                
                // Преобразуем полигон в массив точек [{lat, lng}, ...]
                $points = [];
                foreach ($polygon as $point) {
                    if (isset($point['lat']) && isset($point['lng'])) {
                        $points[] = ['lat' => (float)$point['lat'], 'lng' => (float)$point['lng']];
                    } elseif (isset($point[0]) && isset($point[1])) {
                        // Альтернативный формат [lng, lat]
                        $points[] = ['lat' => (float)$point[1], 'lng' => (float)$point[0]];
                    }
                }
                
                if (empty($points)) {
                    continue;
                }
                
                // Проверяем, находится ли точка внутри полигона
                if ($this->pointInPolygon($latitude, $longitude, $points)) {
                    return $zone;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Проверяет, находится ли точка внутри полигона (алгоритм Ray Casting)
     * 
     * @param float $lat Широта точки
     * @param float $lng Долгота точки
     * @param array $polygon Массив точек полигона [{lat, lng}, ...]
     * @return bool
     */
    protected function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $inside = false;
        $j = count($polygon) - 1;
        
        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i]['lng'];
            $yi = $polygon[$i]['lat'];
            $xj = $polygon[$j]['lng'];
            $yj = $polygon[$j]['lat'];
            
            $intersect = (($yi > $lat) != ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
            
            if ($intersect) {
                $inside = !$inside;
            }
            
            $j = $i;
        }
        
        return $inside;
    }
    
    /**
     * Возвращает зону по умолчанию (Blue)
     */
    protected function getDefaultZone(): ?DeliveryZone
    {
        return DeliveryZone::where('name', 'Blue')->where('is_active', true)->first();
    }

    /**
     * Определяет зону доставки по названию улицы (упрощенный вариант)
     * Используется как fallback, если нет координат
     * 
     * @param string|null $street
     * @return DeliveryZone|null
     */
    protected function resolveZoneByStreet(?string $street): ?DeliveryZone
    {
        if (!$street) {
            return null;
        }

        // Упрощенная логика: определяем зону по префиксу в названии улицы
        // В реальности нужно использовать более сложную логику
        
        // По умолчанию возвращаем Blue зону (средняя)
        return DeliveryZone::where('name', 'Blue')->where('is_active', true)->first();
    }
}
