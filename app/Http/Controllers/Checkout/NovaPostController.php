<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Services\NovaPostApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NovaPostController extends Controller
{
    public function cities(Request $request, NovaPostApiClient $novaPost): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $query = trim((string) ($data['q'] ?? ''));
        if ($query === '') {
            return response()->json(['cities' => []]);
        }

        try {
            return response()->json([
                'cities' => $novaPost->searchCities($query, (int) ($data['limit'] ?? 20)),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'cities' => [],
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function warehouses(Request $request, NovaPostApiClient $novaPost): JsonResponse
    {
        $data = $request->validate([
            'city_ref' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'type' => ['nullable', 'string', 'in:warehouse,postomat'],
        ]);

        try {
            return response()->json([
                'warehouses' => $novaPost->searchWarehouses(
                    (string) $data['city_ref'],
                    (string) ($data['q'] ?? ''),
                    (int) ($data['limit'] ?? 30),
                    (string) ($data['type'] ?? 'warehouse'),
                ),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'warehouses' => [],
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function streets(Request $request, NovaPostApiClient $novaPost): JsonResponse
    {
        $data = $request->validate([
            'city_ref' => ['required', 'string', 'max:80'],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            return response()->json([
                'streets' => $novaPost->searchStreets(
                    (string) $data['city_ref'],
                    (string) ($data['q'] ?? ''),
                    (int) ($data['limit'] ?? 20),
                ),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'streets' => [],
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function deliveryPrices(Request $request, NovaPostApiClient $novaPost): JsonResponse
    {
        $data = $request->validate([
            'city_ref' => ['required', 'string', 'max:80'],
        ]);

        try {
            return response()->json([
                'prices' => $novaPost->deliveryPrices((string) $data['city_ref']),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'prices' => [],
                'message' => $exception->getMessage(),
            ], 503);
        }
    }
}
