<?php

namespace App\Services;

use App\Models\DeliveryZone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/**
 * Сервис для определения зоны доставки по координатам
 * Использует те же полигоны, что и в map-cart.js
 */
class DeliveryZoneResolverService
{
    protected ?array $deliveryAreas = null;
    
    /**
     * Определяет зону доставки по координатам
     * Использует полигоны из map-cart.js
     */
    public function resolveZoneByCoordinates(float $latitude, float $longitude): ?DeliveryZone
    {
        // Загружаем полигоны из map-cart.js
        $areas = $this->loadDeliveryAreas();
        
        if (empty($areas)) {
            Log::warning('DeliveryZoneResolver: No delivery areas loaded, falling back to distance');
            return $this->resolveByDistance($latitude, $longitude);
        }
        
        // Проверяем точку внутри каждого полигона
        foreach ($areas as $areaKey => $area) {
            if (!isset($area['area']) || !is_array($area['area'])) {
                continue;
            }
            
            $polygon = $area['area'];
            
            // Проверяем, находится ли точка внутри полигона
            if ($this->pointInPolygon($latitude, $longitude, $polygon)) {
                // Определяем группу зоны по префиксу (Green_, Blue_, Red_, Brown_)
                $zoneName = $this->extractZoneName($areaKey);
                
                Log::info('DeliveryZoneResolver: Zone found by polygon', [
                    'area_key' => $areaKey,
                    'zone_name' => $zoneName,
                    'lat' => $latitude,
                    'lng' => $longitude,
                ]);
                
                return DeliveryZone::where('name', $zoneName)->where('is_active', true)->first();
            }
        }
        
        // Если не нашли по полигонам, используем расстояние
        Log::info('DeliveryZoneResolver: Zone not found by polygon, using distance');
        return $this->resolveByDistance($latitude, $longitude);
    }
    
    /**
     * Загружает полигоны из map-cart.js
     */
    protected function loadDeliveryAreas(): array
    {
        if ($this->deliveryAreas !== null) {
            return $this->deliveryAreas;
        }
        
        // Пробуем загрузить из кеша
        $cacheKey = 'delivery_areas_parsed';
        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            $this->deliveryAreas = $cached;
            return $this->deliveryAreas;
        }
        
        $filePath = $this->resolveDeliveryAreasFilePath();
        
        if (!File::exists($filePath)) {
            Log::warning('DeliveryZoneResolver: map-cart.js not found');
            return [];
        }
        
        $content = File::get($filePath);
        
        // Парсим JavaScript объект в PHP массив
        $this->deliveryAreas = $this->parseDeliveryAreas($content);
        
        // Кешируем результат на 24 часа
        if (!empty($this->deliveryAreas)) {
            cache()->put($cacheKey, $this->deliveryAreas, now()->addHours(24));
            Log::info('DeliveryZoneResolver: Loaded and cached delivery areas', [
                'count' => count($this->deliveryAreas),
            ]);
        }
        
        return $this->deliveryAreas;
    }

    protected function resolveDeliveryAreasFilePath(): string
    {
        $candidates = [
            resource_path('js/map-cart.js'),
            base_path('packages/frontend-' . config('project.theme', '3piroga') . '/resources/js/map-cart.js'),
            base_path('packages/frontend-3piroga/resources/js/map-cart.js'),
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0];
    }
    
    /**
     * Парсит deliveryAreas из JavaScript кода
     */
    protected function parseDeliveryAreas(string $content): array
    {
        $areas = [];
        
        // Ищем начало объекта deliveryAreas
        if (!preg_match('/var\s+deliveryAreas\s*=\s*\{/s', $content, $startMatch, PREG_OFFSET_CAPTURE)) {
            Log::warning('DeliveryZoneResolver: Could not find deliveryAreas object');
            return [];
        }
        
        $startPos = $startMatch[0][1] + strlen($startMatch[0][0]);
        
        // Находим конец объекта deliveryAreas (ищем закрывающую скобку на том же уровне вложенности)
        $braceCount = 1;
        $pos = $startPos;
        $endPos = strlen($content);
        
        while ($pos < strlen($content) && $braceCount > 0) {
            $char = $content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $endPos = $pos;
                    break;
                }
            }
            $pos++;
        }
        
        $areasContent = substr($content, $startPos, $endPos - $startPos);
        
        // Парсим каждую зону: ZoneName_Number: { area: [...], color: '...' }
        // Используем более точное регулярное выражение
        $pattern = '/(\w+_\d+):\s*\{[^}]*area:\s*\[([^\]]+(?:\{[^\}]+\}[^\]]*)*)\][^}]*color:\s*[\'"]?([^\'",}\s]+)[\'"]?/s';
        
        if (preg_match_all($pattern, $areasContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = trim($match[1]);
                $areaStr = $match[2];
                $color = trim($match[3] ?? '#000000');
                
                // Парсим массив точек area
                $points = $this->parsePoints($areaStr);
                
                if (!empty($points)) {
                    $areas[$key] = [
                        'area' => $points,
                        'color' => $color,
                    ];
                }
            }
        } else {
            Log::warning('DeliveryZoneResolver: Could not parse deliveryAreas structure', [
                'areas_content_length' => strlen($areasContent),
            ]);
        }
        
        return $areas;
    }
    
    /**
     * Парсит массив точек из строки вида: { lng: 30.123, lat: 50.456 }, { lng: 30.789, lat: 50.012 }, ...
     */
    protected function parsePoints(string $areaStr): array
    {
        $points = [];
        
        // Ищем все точки в формате: { lng: число, lat: число }
        $pattern = '/\{\s*lng:\s*([\d.]+),\s*lat:\s*([\d.]+)\s*\}/';
        
        if (preg_match_all($pattern, $areaStr, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $points[] = [
                    'lng' => (float)$match[1],
                    'lat' => (float)$match[2],
                ];
            }
        }
        
        return $points;
    }
    
    /**
     * Извлекает название зоны из ключа (Green_1 -> Green, Blue_5 -> Blue)
     */
    protected function extractZoneName(string $areaKey): string
    {
        if (preg_match('/^(\w+)_\d+$/', $areaKey, $matches)) {
            return $matches[1];
        }
        
        return 'Blue'; // По умолчанию
    }
    
    /**
     * Проверяет, находится ли точка внутри полигона (алгоритм Ray Casting)
     */
    protected function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        if (empty($polygon)) {
            return false;
        }
        
        $inside = false;
        $j = count($polygon) - 1;
        
        for ($i = 0; $i < count($polygon); $i++) {
            $pointI = $polygon[$i];
            $pointJ = $polygon[$j];
            
            $xi = $pointI['lng'] ?? $pointI[0] ?? 0;
            $yi = $pointI['lat'] ?? $pointI[1] ?? 0;
            $xj = $pointJ['lng'] ?? $pointJ[0] ?? 0;
            $yj = $pointJ['lat'] ?? $pointJ[1] ?? 0;
            
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
     * Определяет зону по расстоянию (fallback)
     */
    protected function resolveByDistance(float $latitude, float $longitude): ?DeliveryZone
    {
        // Центр из map-cart.js: const CENTER = { lat: 50.4590851, lng: 30.4182548 }
        $centerLat = 50.4590851;
        $centerLng = 30.4182548;
        
        $distance = $this->calculateDistance($latitude, $longitude, $centerLat, $centerLng);
        
        Log::info('DeliveryZoneResolver: Resolving by distance', [
            'lat' => $latitude,
            'lng' => $longitude,
            'distance_km' => round($distance, 2),
        ]);
        
        if ($distance <= 3) {
            return DeliveryZone::where('name', 'Green')->where('is_active', true)->first();
        } elseif ($distance <= 8) {
            return DeliveryZone::where('name', 'Blue')->where('is_active', true)->first();
        } elseif ($distance <= 12) {
            return DeliveryZone::where('name', 'Red')->where('is_active', true)->first();
        } else {
            return DeliveryZone::where('name', 'Brown')->where('is_active', true)->first();
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
}
