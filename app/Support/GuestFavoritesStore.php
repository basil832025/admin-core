<?php

namespace App\Support;

use App\Models\Shop\Client;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

class GuestFavoritesStore
{
    public const COOKIE_KEY = 'guest_favorites';
    private const COOKIE_MINUTES = 60 * 24 * 30;

    public static function idsFromRequest(): array
    {
        $raw = request()->cookie(self::COOKIE_KEY);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function queueIds(array $ids): void
    {
        $normalized = collect($ids)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            Cookie::queue(Cookie::forget(self::COOKIE_KEY));

            return;
        }

        Cookie::queue(cookie(self::COOKIE_KEY, json_encode($normalized), self::COOKIE_MINUTES, '/', null, null, false, false, 'lax'));
    }

    public static function mergeToClient(?Client $client): void
    {
        if (! $client) {
            return;
        }

        $ids = self::idsFromRequest();
        if ($ids === []) {
            return;
        }

        $existing = DB::table('bs_favorites')
            ->where('client_id', $client->id)
            ->pluck('product_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $toInsert = array_values(array_diff($ids, $existing));
        if ($toInsert !== []) {
            $now = now();
            DB::table('bs_favorites')->insert(array_map(fn ($productId) => [
                'client_id' => $client->id,
                'product_id' => $productId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $toInsert));
        }

        Cookie::queue(Cookie::forget(self::COOKIE_KEY));
    }
}
