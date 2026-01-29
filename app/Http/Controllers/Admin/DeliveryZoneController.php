<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryZoneController extends Controller
{
    /**
     * Определяет зону доставки по координатам
     * Использует ту же логику, что и на странице /delivery
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resolveZone(Request $request)
    {
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');

        if (!$latitude || !$longitude) {
            return response()->json([
                'success' => false,
                'message' => 'Координаты не указаны',
            ], 400);
        }

        // Используем Google Maps Geometry API для проверки точки внутри полигонов
        // Но для этого нужно иметь полигоны в базе данных
        // Пока используем упрощенную логику по расстоянию
        
        // В будущем можно улучшить, сохранив полигоны в базу данных
        // и используя Google Maps Geometry API для проверки точки внутри полигона
        
        // Используем ту же логику, что и в DeliveryCalculationService
        $centerLat = 50.4501; // Центр Киева
        $centerLng = 30.5234;
        
        $distance = $this->calculateDistance((float)$latitude, (float)$longitude, $centerLat, $centerLng);
        
        // Определяем зону по расстоянию (примерные значения)
        // Green: до 5 км
        // Blue: 5-10 км
        // Red: 10-15 км
        // Brown: более 15 км
        
        $zone = null;
        if ($distance <= 5) {
            $zone = DeliveryZone::where('name', 'Green')->where('is_active', true)->first();
        } elseif ($distance <= 10) {
            $zone = DeliveryZone::where('name', 'Blue')->where('is_active', true)->first();
        } elseif ($distance <= 15) {
            $zone = DeliveryZone::where('name', 'Red')->where('is_active', true)->first();
        } else {
            $zone = DeliveryZone::where('name', 'Brown')->where('is_active', true)->first();
        }

        if (!$zone) {
            return response()->json([
                'success' => false,
                'message' => 'Зона доставки не найдена',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->name,
                'color' => $zone->color,
                'delivery_price' => (float)$zone->delivery_price,
                'delivery_time_min' => (int)$zone->delivery_time_min,
                'delivery_time_max' => (int)$zone->delivery_time_max,
                'free_delivery_from' => (float)$zone->free_delivery_from,
            ],
        ]);
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
