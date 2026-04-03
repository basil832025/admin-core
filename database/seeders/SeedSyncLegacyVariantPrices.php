<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncLegacyVariantPrices extends Seeder
{
    public function run(): void
    {
        $legacy = $this->legacyConnection();

        $legacyProducts = [];
        foreach ($legacy->table('catalog_products')->where('hidden', 0)->select(['id'])->get() as $row) {
            $legacyProducts[(int) $row->id] = true;
        }

        $legacyPriceBySize = [];
        $legacyRows = $legacy->table('catalog_params as cp')
            ->leftJoin('catalog_values as cv', 'cv.id', '=', 'cp.value_id')
            ->where('cp.active', 1)
            ->select(['cp.product_id', 'cp.price', 'cv.value'])
            ->orderBy('cp.product_id')
            ->orderBy('cp.sort')
            ->orderBy('cp.id')
            ->get();

        foreach ($legacyRows as $row) {
            $productId = (int) $row->product_id;
            if (! isset($legacyProducts[$productId])) {
                continue;
            }

            $sizeKey = $this->normalizeNumber((string) ($row->value ?? ''));
            if ($sizeKey === null) {
                continue;
            }

            if (! isset($legacyPriceBySize[$productId][$sizeKey])) {
                $legacyPriceBySize[$productId][$sizeKey] = (float) $row->price;
            }
        }

        $newPriceBySize = [];
        $sizeRows = DB::table('bs_products as p')
            ->join('bs_products as p2', function ($join): void {
                $join->on('p2.id', '=', 'p.id')
                    ->orOn('p2.parent_id', '=', 'p.id');
            })
            ->join('bs_product_characteristic_value as pcv', 'pcv.product_id', '=', 'p2.id')
            ->join('bs_characteristic_values as cv', 'cv.id', '=', 'pcv.characteristic_value_id')
            ->whereNull('p.parent_id')
            ->whereIn('pcv.characteristic_id', [1, 21, 22, 23, 24])
            ->select(['p.id as parent_id', 'p2.id as product_id', 'cv.value'])
            ->get();

        foreach ($sizeRows as $row) {
            $sizeKey = $this->normalizeSizeValueJson((string) $row->value);
            if ($sizeKey === null) {
                continue;
            }

            $parentId = (int) $row->parent_id;
            if (! isset($newPriceBySize[$parentId][$sizeKey])) {
                $newPriceBySize[$parentId][$sizeKey] = (int) $row->product_id;
            }
        }

        $checked = 0;
        $updated = 0;
        $missingInNew = 0;

        DB::beginTransaction();

        try {
            foreach ($legacyPriceBySize as $parentId => $legacySizes) {
                foreach ($legacySizes as $sizeKey => $legacyPrice) {
                    if (! isset($newPriceBySize[$parentId][$sizeKey])) {
                        $missingInNew++;
                        continue;
                    }

                    $checked++;
                    $productId = $newPriceBySize[$parentId][$sizeKey];

                    $currentPrice = (float) DB::table('bs_products')
                        ->where('id', $productId)
                        ->value('price');

                    if (abs($currentPrice - $legacyPrice) <= 0.009) {
                        continue;
                    }

                    DB::table('bs_products')
                        ->where('id', $productId)
                        ->update([
                            'price' => $legacyPrice,
                            'updated_at' => now(),
                        ]);

                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("Legacy price sync done. Checked: {$checked}, Updated: {$updated}, Missing variants in MyAdmin: {$missingInNew}");
    }

    private function legacyConnection(): Connection
    {
        $host = env('LEGACY_DB_HOST');
        if (! $host) {
            return DB::connection();
        }

        config([
            'database.connections.legacy_sync' => [
                'driver' => 'mysql',
                'host' => $host,
                'port' => env('LEGACY_DB_PORT', 3306),
                'database' => env('LEGACY_DB_DATABASE', ''),
                'username' => env('LEGACY_DB_USERNAME', ''),
                'password' => env('LEGACY_DB_PASSWORD', ''),
                'charset' => env('LEGACY_DB_CHARSET', 'utf8'),
                'collation' => env('LEGACY_DB_COLLATION', 'utf8_general_ci'),
                'prefix' => '',
                'strict' => false,
                'engine' => null,
            ],
        ]);

        return DB::connection('legacy_sync');
    }

    private function normalizeSizeValueJson(string $rawValue): ?string
    {
        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            $preferred = (string) ($decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? reset($decoded) ?? '');
            return $this->normalizeNumber($preferred);
        }

        return $this->normalizeNumber($rawValue);
    }

    private function normalizeNumber(string $raw): ?string
    {
        $normalized = str_replace(',', '.', trim($raw));
        if (preg_match('/\d+(?:\.\d+)?/', $normalized, $matches) !== 1) {
            return null;
        }

        $number = (float) $matches[0];
        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
