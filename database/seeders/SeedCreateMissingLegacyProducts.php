<?php

namespace Database\Seeders;

use Illuminate\Database\Connection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedCreateMissingLegacyProducts extends Seeder
{
    private array $sizeCharacteristicIds = [1, 21, 22, 23, 24];

    public function run(): void
    {
        $legacy = $this->legacyConnection();
        $valueMap = $this->buildValueMapByCharacteristic(array_merge($this->sizeCharacteristicIds, [2, 3]));

        $legacyProducts = $legacy->table('catalog_products')
            ->where('hidden', 0)
            ->orderBy('id')
            ->get();

        $createdParents = 0;
        $createdVariants = 0;
        $skippedNoParams = 0;
        $skippedNoCategory = 0;

        DB::beginTransaction();

        try {
            foreach ($legacyProducts as $legacyProduct) {
                $legacyId = (int) $legacyProduct->id;

                $existingParent = DB::table('bs_products')->where('id', $legacyId)->whereNull('parent_id')->first();
                if ($existingParent) {
                    continue;
                }

                $categoryExists = DB::table('bs_product_categories')
                    ->where('id', (int) $legacyProduct->category_id)
                    ->exists();

                if (! $categoryExists) {
                    $skippedNoCategory++;
                    continue;
                }

                $params = $legacy->table('catalog_params as cp')
                    ->leftJoin('catalog_values as cv', 'cv.id', '=', 'cp.value_id')
                    ->leftJoin('catalog_weight as cw', 'cw.id', '=', 'cp.weight_id')
                    ->where('cp.product_id', $legacyId)
                    ->where('cp.active', 1)
                    ->orderBy('cp.sort')
                    ->orderBy('cp.id')
                    ->select([
                        'cp.type_id',
                        'cp.price',
                        'cp.coded2',
                        'cp.count_person',
                        'cv.value as size_value',
                        'cw.value as weight_value',
                    ])
                    ->get();

                if ($params->isEmpty()) {
                    $skippedNoParams++;
                    continue;
                }

                $infoRows = $legacy->table('catalog_products_info')
                    ->where('record_id', $legacyId)
                    ->get();

                $title = $this->buildLocalizedJson($infoRows, 'title', (string) $legacyProduct->alias);
                $description = $this->buildLocalizedJson($infoRows, 'text');
                $shortDescription = $this->pickLocalizedText($infoRows, 'short_desc');
                $shortTitle = $this->pickLocalizedText($infoRows, 'short_title');

                $firstParam = $this->toParamArray($params->first());
                $firstSizeKey = $this->normalizeNumber((string) ($firstParam['size_value'] ?? ''));
                $firstSizeKey = $firstSizeKey ?? '0';

                $slug = $this->makeUniqueSlug((string) $legacyProduct->alias);
                $isNew = in_array((string) ($legacyProduct->new ?? ''), ['1', 'true', 'yes'], true) ? 1 : 0;

                $insertParent = [
                    'id' => $legacyId,
                    'is_new' => $isNew,
                    'is_hit' => 0,
                    'is_home' => 0,
                    'code2' => $firstParam['coded2'] ?: null,
                    'is_imported' => 0,
                    'import_source_id' => null,
                    'sort' => (int) ($legacyProduct->sort ?? 0),
                    'parent_id' => null,
                    'category_id' => (int) $legacyProduct->category_id,
                    'sku' => null,
                    'title' => $title,
                    'short_desc' => $shortDescription !== '' ? $shortDescription : null,
                    'short_name' => $shortTitle !== '' ? $shortTitle : null,
                    'slug' => $slug,
                    'main_image' => 'products/main/' . $legacyId . '.1.b.png',
                    'main_image_small' => 'products/small/' . $legacyId . '.1.s.jpg',
                    'description' => $description,
                    'dop_info' => $firstSizeKey,
                    'price' => (float) $firstParam['price'],
                    'old_price' => null,
                    'in_stock' => 1,
                    'quantity' => 0,
                    'seo_title' => null,
                    'seo_description' => null,
                    'seo_keywords' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $idTaken = DB::table('bs_products')->where('id', $legacyId)->exists();
                if ($idTaken) {
                    unset($insertParent['id']);
                }

                $parentId = (int) DB::table('bs_products')->insertGetId($insertParent);
                $createdParents++;

                $this->applyParamToProduct($parentId, $firstParam, $valueMap);

                $rest = $params->slice(1);
                foreach ($rest as $row) {
                    $param = $this->toParamArray($row);
                    $variantId = $this->createVariantFromParent($parentId, $param);
                    $this->applyParamToProduct($variantId, $param, $valueMap);
                    $createdVariants++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("Missing legacy products sync done. Created parents: {$createdParents}, Created variants: {$createdVariants}, Skipped(no params): {$skippedNoParams}, Skipped(no category): {$skippedNoCategory}");
    }

    private function applyParamToProduct(int $productId, array $param, array $valueMap): void
    {
        $sizeKey = $this->normalizeNumber((string) ($param['size_value'] ?? ''));
        if ($sizeKey === null) {
            return;
        }

        $resolved = $this->resolveSizeCharacteristic((int) $param['type_id'], $sizeKey, $valueMap);
        if ($resolved === null) {
            return;
        }

        DB::table('bs_products')
            ->where('id', $productId)
            ->update([
                'price' => (float) $param['price'],
                'code2' => $param['coded2'] ?: DB::raw('code2'),
                'dop_info' => $sizeKey,
                'updated_at' => now(),
            ]);

        $this->replaceCharacteristicValues($productId, $this->sizeCharacteristicIds, [[
            'characteristic_id' => $resolved['characteristic_id'],
            'value_id' => $resolved['value_id'],
        ]]);

        if ((int) $param['type_id'] !== 3) {
            return;
        }

        $extraValues = [];

        $weightKey = $this->normalizeNumber((string) ($param['weight_value'] ?? ''));
        if ($weightKey !== null && isset($valueMap[2][$weightKey])) {
            $extraValues[] = [
                'characteristic_id' => 2,
                'value_id' => $valueMap[2][$weightKey],
            ];
        }

        $personValueId = $this->resolvePersonsValueId((int) ($param['count_person'] ?? 0), $valueMap);
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

    private function createVariantFromParent(int $parentId, array $param): int
    {
        $parent = DB::table('bs_products')->where('id', $parentId)->first();
        if (! $parent) {
            throw new \RuntimeException("Parent product {$parentId} not found.");
        }

        $sizeKey = $this->normalizeNumber((string) ($param['size_value'] ?? '')) ?? '0';
        $sizeSlugPart = str_replace('.', '-', $sizeKey);

        $existing = DB::table('bs_products')
            ->where('parent_id', $parentId)
            ->where(function ($query) use ($parent, $sizeKey, $sizeSlugPart): void {
                $query->where('dop_info', $sizeKey)
                    ->orWhere('slug', $parent->slug . '_' . $sizeSlugPart);
            })
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        return (int) DB::table('bs_products')->insertGetId([
            'is_new' => 0,
            'is_hit' => 0,
            'is_home' => 0,
            'code2' => $param['coded2'] ?: null,
            'is_imported' => 0,
            'import_source_id' => null,
            'sort' => 0,
            'parent_id' => $parentId,
            'category_id' => $parent->category_id,
            'sku' => null,
            'title' => $parent->title,
            'short_desc' => null,
            'short_name' => $this->buildShortName((string) $parent->title, (string) ($parent->short_name ?? ''), $sizeKey),
            'slug' => $this->makeUniqueSlug($parent->slug . '_' . $sizeSlugPart),
            'main_image' => null,
            'main_image_small' => null,
            'description' => null,
            'dop_info' => $sizeKey,
            'price' => (float) $param['price'],
            'old_price' => null,
            'in_stock' => 1,
            'quantity' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_keywords' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function toParamArray(object $row): array
    {
        return [
            'type_id' => (int) $row->type_id,
            'price' => (float) $row->price,
            'coded2' => $row->coded2,
            'count_person' => (int) ($row->count_person ?? 0),
            'size_value' => $row->size_value,
            'weight_value' => $row->weight_value,
        ];
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

    private function buildLocalizedJson($infoRows, string $column, string $fallback = ''): string
    {
        $result = ['ru' => $fallback, 'uk' => $fallback, 'en' => $fallback];
        foreach ($infoRows as $row) {
            $langKey = $this->legacyLangToLocale((string) $row->lang);
            if ($langKey === null) {
                continue;
            }

            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '') {
                $result[$langKey] = $value;
            }
        }

        if ($result['uk'] === '' && $result['ru'] !== '') {
            $result['uk'] = $result['ru'];
        }
        if ($result['ru'] === '' && $result['uk'] !== '') {
            $result['ru'] = $result['uk'];
        }
        if ($result['en'] === '') {
            $result['en'] = $result['uk'] !== '' ? $result['uk'] : $result['ru'];
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"ru":"","uk":"","en":""}';
    }

    private function pickLocalizedText($infoRows, string $column): string
    {
        $byLocale = ['uk' => '', 'ru' => '', 'en' => ''];
        foreach ($infoRows as $row) {
            $langKey = $this->legacyLangToLocale((string) $row->lang);
            if ($langKey === null) {
                continue;
            }

            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '' && $byLocale[$langKey] === '') {
                $byLocale[$langKey] = $value;
            }
        }

        return $byLocale['uk'] ?: ($byLocale['ru'] ?: $byLocale['en']);
    }

    private function legacyLangToLocale(string $legacyLang): ?string
    {
        return match ($legacyLang) {
            '1' => 'ru',
            '2' => 'uk',
            '3' => 'en',
            default => null,
        };
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
