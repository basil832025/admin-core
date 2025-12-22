<?php

namespace App\Support;

use App\Models\Shop\Client;
use Illuminate\Support\Facades\DB;

class FavoritesSync
{
    public const SESSION_KEY = 'favorites';

    public static function mergeSessionToClient(?Client $client): void
    {
        if (!$client) return;

        $ids = collect((array) session(self::SESSION_KEY, []))
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if (!$ids) return;

        // уже существующие у клиента
        $existing = DB::table('bs_favorites')
            ->where('client_id', $client->id)
            ->pluck('product_id')
            ->map(fn($v) => (int) $v)
            ->all();

        $toInsert = array_values(array_diff($ids, $existing));
        if ($toInsert) {
            $now = now();
            $rows = array_map(fn($pid) => [
                'client_id'  => $client->id,
                'product_id' => $pid,
                'created_at' => $now,
                'updated_at' => $now,
            ], $toInsert);

            DB::table('bs_favorites')->insert($rows);
        }

        session()->forget(self::SESSION_KEY);
    }
}
