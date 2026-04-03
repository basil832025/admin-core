<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncMissingLegacySizesForExistingProducts extends Seeder
{
    private array $sizeCharacteristicIds = [1, 21, 22, 23, 24];

    public function run(): void
    {
        $legacy = $this->legacyConnection();

        $sizeValueMap = $this->buildValueMapByCharacteristic(array_merge($this->sizeCharacteristicIds, [2, 3]));

        $legacyParamsByProduct = [];
        $legacyRows = $legacy->table('catalog_params as cp')
            ->join('catalog_products as p', 'p.id', '=', 'cp.product_id')
            ->leftJoin('catalog_values as cv', 'cv.id', '=', 'cp.value_id')
            ->leftJoin('catalog_weight as cw', 'cw.id', '=', 'cp.weight_id')
            ->where('cp.active', 1)
            ->where('p.hidden', 0)
            ->select([
                'cp.product_id',
                'cp.type_id',
                'cp.price',
                'cp.coded2',
                'cp.count_person',
                'cp.sort',
                'cp.id',
                'cv.value as size_value',
                'cw.value as weight_value',
            ])
            ->orderBy('cp.product_id')
            ->orderBy('cp.sort')
            ->orderBy('cp.id')
            ->get();

        foreach ($legacyRows as $row) {
            $productId = (int) $row->product_id;
            $sizeKey = $this->normalizeNumber((string) ($row->size_value ?? ''));
            if ($sizeKey === null) {
                continue;
            }

            if (! isset($legacyParamsByProduct[$productId])) {
                $legacyParamsByProduct[$productId] = [];
            }

            if (! isset($legacyParamsByProduct[$productId][$sizeKey])) {
                $legacyParamsByProduct[$productId][$sizeKey] = [
                    'type_id' => (int) $row->type_id,
                    'size_key' => $sizeKey,
                    'price' => (float) $row->price,
                    'coded2' => $row->coded2,
                    'count_person' => (int) ($row->count_person ?? 0),
                    'weight_key' => $this->normalizeNumber((string) ($row->weight_value ?? '')),
                ];
            }
        }

        $created = 0;
        $updated = 0;
        $skippedNoParent = 0;
        $skippedNoSizeValue = 0;

        DB::beginTransaction();

        try {
            foreach ($legacyParamsByProduct as $parentId => $paramsBySize) {
                $parent = DB::table('bs_products')->where('id', $parentId)->whereNull('parent_id')->first();
                if (! $parent) {
                    $skippedNoParent++;
                    continue;
                }

                $sizeToProductId = $this->buildFamilySizeMap((int) $parent->id);
                $params = array_values($paramsBySize);

                if (empty($sizeToProductId) && ! empty($params)) {
                    $first = array_shift($params);
                    $resolved = $this->resolveSizeCharacteristic($first['type_id'], $first['size_key'], $sizeValueMap);
                    if ($resolved !== null) {
                        $this->applyParamToProduct((int) $parent->id, $first, $resolved['characteristic_id'], $resolved['value_id'], $sizeValueMap);
                        $sizeToProductId[$first['size_key']] = (int) $parent->id;
                        $updated++;
                    } else {
                        $skippedNoSizeValue++;
                    }
                }

                foreach ($params as $param) {
                    $resolved = $this->resolveSizeCharacteristic($param['type_id'], $param['size_key'], $sizeValueMap);
                    if ($resolved === null) {
                        $skippedNoSizeValue++;
                        continue;
                    }

                    if (isset($sizeToProductId[$param['size_key']])) {
                        $this->applyParamToProduct($sizeToProductId[$param['size_key']], $param, $resolved['characteristic_id'], $resolved['value_id'], $sizeValueMap);
                        $updated++;
                        continue;
                    }

                    $variantId = $this->findOrCreateVariant((int) $parent->id, $param['size_key'], $param);
                    $this->applyParamToProduct($variantId, $param, $resolved['characteristic_id'], $resolved['value_id'], $sizeValueMap);
                    $sizeToProductId[$param['size_key']] = $variantId;
                    $created++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("Legacy size sync for existing products done. Created variants: {$created}, Updated variants: {$updated}, Skipped(no parent): {$skippedNoParent}, Skipped(no size value mapping): {$skippedNoSizeValue}");
    }

    private function applyParamToProduct(int $productId, array $param, int $sizeCharacteristicId, int $sizeValueId, array $valueMap): void
    {
        DB::table('bs_products')
            ->where('id', $productId)
            ->update([
                'price' => $param['price'],
                'code2' => $param['coded2'] ?: DB::raw('code2'),
                'dop_info' => $param['size_key'],
                'updated_at' => now(),
            ]);

        $this->replaceCharacteristicValues($productId, $this->sizeCharacteristicIds, [[
            'characteristic_id' => $sizeCharacteristicId,
            'value_id' => $sizeValueId,
        ]]);

        if ((int) $param['type_id'] !== 3) {
            return;
        }

        $extraValues = [];

        $weightKey = $param['weight_key'];
        if ($weightKey !== null && isset($valueMap[2][$weightKey])) {
            $extraValues[] = [
                'characteristic_id' => 2,
                'value_id' => $valueMap[2][$weightKey],
            ];
        }

        $personValueId = $this->resolvePersonsValueId((int) $param['count_person'], $valueMap);
        if ($personValueId !== null) {
            $extraValues[] = [
                'characteristic_id' => 3,
                'value_id' => $personValueId,
            ];
        }

        if (! empty($extraValues)) {
            $this->replaceCharacteristicValues($productId, [2, 3], $extraValues);
        }
    }

    private function findOrCreateVariant(int $parentId, string $sizeKey, array $param): int
    {
        $parent = DB::table('bs_products')->where('id', $parentId)->first();
        if (! $parent) {
            throw new \RuntimeException("Parent product {$parentId} not found.");
        }

        $sizeLabel = $sizeKey;
        $sizeSlugPart = str_replace('.', '-', $sizeKey);

        $candidate = DB::table('bs_products')
            ->where('parent_id', $parentId)
            ->where(function ($query) use ($parent, $sizeLabel, $sizeSlugPart): void {
                $query->where('dop_info', $sizeLabel)
                    ->orWhere('slug', $parent->slug . '_' . $sizeSlugPart);
            })
            ->orderBy('id')
            ->first();

        if ($candidate) {
            return (int) $candidate->id;
        }

        return (int) DB::table('bs_products')->insertGetId([
            'is_new' => 0,
            'is_hit' => 0,
            'is_home' => 0,
            'code2' => $param['coded2'] ?: null,
            'is_imported' => (int) ($parent->is_imported ?? 0),
            'import_source_id' => $parent->import_source_id,
            'sort' => 0,
            'parent_id' => $parentId,
            'category_id' => $parent->category_id,
            'sku' => null,
            'title' => $parent->title,
            'short_desc' => null,
            'short_name' => $this->buildShortName((string) $parent->title, (string) ($parent->short_name ?? ''), $sizeLabel),
            'slug' => $this->makeUniqueSlug($parent->slug . '_' . $sizeSlugPart),
            'main_image' => null,
            'main_image_small' => null,
            'description' => null,
            'dop_info' => $sizeLabel,
            'price' => $param['price'],
            'old_price' => null,
            'in_stock' => (int) ($parent->in_stock ?? 1),
            'quantity' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildFamilySizeMap(int $parentId): array
    {
        $sizeMap = [];

        $rows = DB::table('bs_products as p2')
            ->join('bs_product_characteristic_value as pcv', 'pcv.product_id', '=', 'p2.id')
            ->join('bs_characteristic_values as cv', 'cv.id', '=', 'pcv.characteristic_value_id')
            ->where(function ($query) use ($parentId): void {
                $query->where('p2.id', $parentId)
                    ->orWhere('p2.parent_id', $parentId);
            })
            ->whereIn('pcv.characteristic_id', $this->sizeCharacteristicIds)
            ->select(['p2.id as product_id', 'cv.value'])
            ->get();

        foreach ($rows as $row) {
            $sizeKey = $this->normalizeSizeValueJson((string) $row->value);
            if ($sizeKey === null) {
                continue;
            }

            if (! isset($sizeMap[$sizeKey])) {
                $sizeMap[$sizeKey] = (int) $row->product_id;
            }
        }

        return $sizeMap;
    }

    private function resolveSizeCharacteristic(int $legacyTypeId, string $sizeKey, array $valueMap): ?array
    {
        $primaryMap = [
            1 => 22,
            2 => 21,
            3 => 1,
        ];

        $fallbackMap = [
            1 => [23],
            2 => [23],
            3 => [24],
        ];

        $primaryCharacteristicId = $primaryMap[$legacyTypeId] ?? null;
        if ($primaryCharacteristicId !== null && isset($valueMap[$primaryCharacteristicId][$sizeKey])) {
            return [
                'characteristic_id' => $primaryCharacteristicId,
                'value_id' => $valueMap[$primaryCharacteristicId][$sizeKey],
            ];
        }

        foreach ($fallbackMap[$legacyTypeId] ?? [] as $characteristicId) {
            if (isset($valueMap[$characteristicId][$sizeKey])) {
                return [
                    'characteristic_id' => $characteristicId,
                    'value_id' => $valueMap[$characteristicId][$sizeKey],
                ];
            }
        }

        $candidates = [];
        foreach ($this->sizeCharacteristicIds as $characteristicId) {
            if (isset($valueMap[$characteristicId][$sizeKey])) {
                $candidates[] = [
                    'characteristic_id' => $characteristicId,
                    'value_id' => $valueMap[$characteristicId][$sizeKey],
                ];
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return null;
    }

    private function resolvePersonsValueId(int $legacyCountPerson, array $valueMap): ?int
    {
        if (! isset($valueMap[3])) {
            return null;
        }

        $target = max(1, $legacyCountPerson - 1);
        $targetKey = (string) $target;
        if (isset($valueMap[3][$targetKey])) {
            return $valueMap[3][$targetKey];
        }

        $fallbackKey = (string) $legacyCountPerson;
        if (isset($valueMap[3][$fallbackKey])) {
            return $valueMap[3][$fallbackKey];
        }

        return null;
    }

    private function replaceCharacteristicValues(int $productId, array $characteristicIdsToReset, array $values): void
    {
        DB::table('bs_product_characteristic_value')
            ->where('product_id', $productId)
            ->whereIn('characteristic_id', $characteristicIdsToReset)
            ->delete();

        foreach ($values as $value) {
            DB::table('bs_product_characteristic_value')->insert([
                'product_id' => $productId,
                'characteristic_id' => $value['characteristic_id'],
                'characteristic_value_id' => $value['value_id'],
                'value_text' => null,
                'value_number' => null,
                'value_datetime' => null,
                'price_modifier' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function buildValueMapByCharacteristic(array $characteristicIds): array
    {
        $map = [];
        $rows = DB::table('bs_characteristic_values')
            ->whereIn('characteristic_id', $characteristicIds)
            ->select(['id', 'characteristic_id', 'value'])
            ->get();

        foreach ($rows as $row) {
            $sizeKey = $this->normalizeSizeValueJson((string) $row->value);
            if ($sizeKey === null) {
                continue;
            }

            $characteristicId = (int) $row->characteristic_id;
            if (! isset($map[$characteristicId])) {
                $map[$characteristicId] = [];
            }

            if (! isset($map[$characteristicId][$sizeKey])) {
                $map[$characteristicId][$sizeKey] = (int) $row->id;
            }
        }

        return $map;
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

    private function makeUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $index = 2;

        while (DB::table('bs_products')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private function buildShortName(string $titleJson, string $fallbackShortName, string $sizeLabel): string
    {
        $name = trim($fallbackShortName);
        if ($name === '') {
            $decoded = json_decode($titleJson, true);
            if (is_array($decoded)) {
                $name = trim((string) ($decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? ''));
            }
        }

        if ($name === '') {
            $name = 'Варіант';
        }

        if (preg_match('/\[.+\]$/', $name) === 1) {
            $name = trim((string) preg_replace('/\[.+\]$/', '', $name));
        }

        return $name . ' [' . $sizeLabel . ']';
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
