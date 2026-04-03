<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedMissingTwentyThreeSizeVariants extends Seeder
{
    public function run(): void
    {
        $sizeCharacteristicId = (int) DB::table('bs_characteristics')
            ->where('slug', 'rozmir-pirogiv')
            ->value('id');

        $weightCharacteristicId = (int) DB::table('bs_characteristics')
            ->where('slug', 'vaga')
            ->value('id');

        $personsCharacteristicId = (int) DB::table('bs_characteristics')
            ->where('slug', 'persons')
            ->value('id');

        if ($sizeCharacteristicId === 0 || $weightCharacteristicId === 0 || $personsCharacteristicId === 0) {
            $this->command?->error('Required characteristics (size/weight/persons) were not found. Seeder aborted.');
            return;
        }

        $size23ValueId = $this->findCharacteristicValueIdByNumber($sizeCharacteristicId, 23);
        if ($size23ValueId === null) {
            $this->command?->error('Characteristic value for size 23 was not found. Seeder aborted.');
            return;
        }

        $weightValueMap = $this->buildCharacteristicNumberMap($weightCharacteristicId);
        $personsValueMap = $this->buildCharacteristicNumberMap($personsCharacteristicId);

        $legacyRows = DB::table('catalog_params as cp')
            ->join('catalog_values as cv', 'cv.id', '=', 'cp.value_id')
            ->where('cp.active', 1)
            ->where('cv.value', '23')
            ->select([
                'cp.product_id',
                'cp.price',
                'cp.weight_id',
                'cp.count_person',
                'cp.coded2',
            ])
            ->orderBy('cp.product_id')
            ->get();

        $created = 0;
        $updated = 0;
        $skippedNoParent = 0;
        $skippedAlreadyPresent = 0;

        DB::beginTransaction();

        try {
            foreach ($legacyRows as $legacyRow) {
                $parent = DB::table('bs_products')
                    ->where('id', (int) $legacyRow->product_id)
                    ->whereNull('parent_id')
                    ->first();

                if (! $parent) {
                    $skippedNoParent++;
                    continue;
                }

                $hasSize23 = DB::table('bs_products as p')
                    ->join('bs_product_characteristic_value as pcv', 'pcv.product_id', '=', 'p.id')
                    ->where(function ($query) use ($parent): void {
                        $query->where('p.id', (int) $parent->id)
                            ->orWhere('p.parent_id', (int) $parent->id);
                    })
                    ->where('pcv.characteristic_id', $sizeCharacteristicId)
                    ->where('pcv.characteristic_value_id', $size23ValueId)
                    ->exists();

                if ($hasSize23) {
                    $skippedAlreadyPresent++;
                    continue;
                }

                $variant = DB::table('bs_products')
                    ->where('parent_id', (int) $parent->id)
                    ->where(function ($query) use ($parent): void {
                        $query->where('slug', $parent->slug . '_23')
                            ->orWhere('dop_info', '23');
                    })
                    ->orderBy('id')
                    ->first();

                $weightNumber = (int) (DB::table('catalog_weight')->where('id', (int) $legacyRow->weight_id)->value('value') ?? 0);
                $personsNumber = (int) ($legacyRow->count_person ?? 0);
                $weightValueId = $weightValueMap[$weightNumber] ?? null;
                $personsValueId = $personsValueMap[$personsNumber] ?? null;

                if (! $variant) {
                    $variantId = DB::table('bs_products')->insertGetId([
                        'is_new' => 0,
                        'is_hit' => 0,
                        'is_home' => 0,
                        'code2' => $legacyRow->coded2 ?: null,
                        'is_imported' => (int) ($parent->is_imported ?? 0),
                        'import_source_id' => $parent->import_source_id,
                        'sort' => 0,
                        'parent_id' => (int) $parent->id,
                        'category_id' => $parent->category_id,
                        'sku' => null,
                        'title' => $parent->title,
                        'short_desc' => null,
                        'short_name' => $this->buildShortName($parent->title, $parent->short_name, 23),
                        'slug' => $this->makeUniqueSlug($parent->slug . '_23'),
                        'main_image' => null,
                        'main_image_small' => null,
                        'description' => null,
                        'dop_info' => '23',
                        'price' => $legacyRow->price,
                        'old_price' => null,
                        'in_stock' => 1,
                        'quantity' => 0,
                        'seo_title' => null,
                        'seo_description' => null,
                        'seo_keywords' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $created++;
                } else {
                    $variantId = (int) $variant->id;

                    DB::table('bs_products')
                        ->where('id', $variantId)
                        ->update([
                            'code2' => $legacyRow->coded2 ?: $variant->code2,
                            'dop_info' => '23',
                            'price' => $legacyRow->price,
                            'short_name' => $variant->short_name ?: $this->buildShortName($parent->title, $parent->short_name, 23),
                            'updated_at' => now(),
                        ]);

                    $updated++;
                }

                DB::table('bs_product_characteristic_value')
                    ->where('product_id', $variantId)
                    ->whereIn('characteristic_id', [$sizeCharacteristicId, $weightCharacteristicId, $personsCharacteristicId])
                    ->delete();

                $this->insertProductCharacteristic($variantId, $sizeCharacteristicId, $size23ValueId);

                if ($weightValueId !== null) {
                    $this->insertProductCharacteristic($variantId, $weightCharacteristicId, $weightValueId);
                }

                if ($personsValueId !== null) {
                    $this->insertProductCharacteristic($variantId, $personsCharacteristicId, $personsValueId);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("23cm variants done. Created: {$created}, Updated: {$updated}, Skipped(no parent): {$skippedNoParent}, Skipped(already has 23): {$skippedAlreadyPresent}");
    }

    private function insertProductCharacteristic(int $productId, int $characteristicId, int $valueId): void
    {
        DB::table('bs_product_characteristic_value')->insert([
            'product_id' => $productId,
            'characteristic_id' => $characteristicId,
            'characteristic_value_id' => $valueId,
            'value_text' => null,
            'value_number' => null,
            'value_datetime' => null,
            'price_modifier' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buildCharacteristicNumberMap(int $characteristicId): array
    {
        $map = [];

        $rows = DB::table('bs_characteristic_values')
            ->where('characteristic_id', $characteristicId)
            ->select(['id', 'value'])
            ->get();

        foreach ($rows as $row) {
            $number = $this->extractNumberFromValue($row->value);
            if ($number === null) {
                continue;
            }

            $map[$number] = (int) $row->id;
        }

        return $map;
    }

    private function findCharacteristicValueIdByNumber(int $characteristicId, int $targetNumber): ?int
    {
        $rows = DB::table('bs_characteristic_values')
            ->where('characteristic_id', $characteristicId)
            ->select(['id', 'value'])
            ->get();

        foreach ($rows as $row) {
            $number = $this->extractNumberFromValue($row->value);
            if ($number === $targetNumber) {
                return (int) $row->id;
            }
        }

        return null;
    }

    private function extractNumberFromValue(?string $rawValue): ?int
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $decoded = json_decode($rawValue, true);

        $texts = [];
        if (is_array($decoded)) {
            $texts = array_values(array_filter($decoded, fn ($v) => is_string($v) && $v !== ''));
        } else {
            $texts = [$rawValue];
        }

        foreach ($texts as $text) {
            if (preg_match('/\d+/', $text, $matches) === 1) {
                return (int) $matches[0];
            }
        }

        return null;
    }

    private function buildShortName(?string $titleJson, ?string $fallbackShortName, int $size): string
    {
        $baseName = trim((string) $fallbackShortName);

        if ($baseName === '') {
            $decoded = is_string($titleJson) ? json_decode($titleJson, true) : null;
            if (is_array($decoded)) {
                $baseName = trim((string) ($decoded['uk'] ?? $decoded['ru'] ?? $decoded['en'] ?? ''));
            }
        }

        if ($baseName === '') {
            $baseName = 'Варіант';
        }

        if (preg_match('/\[\d+\]$/', $baseName) === 1) {
            $baseName = preg_replace('/\[\d+\]$/', '', $baseName) ?: $baseName;
            $baseName = trim($baseName);
        }

        return $baseName . ' [' . $size . ']';
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
}
