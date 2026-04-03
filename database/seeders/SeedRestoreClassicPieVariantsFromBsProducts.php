<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedRestoreClassicPieVariantsFromBsProducts extends Seeder
{
    /**
     * Canonical variant matrix taken from current bs_products data.
     */
    private array $matrix = [
        'pirog-s-kurnum-file-surom-i-zelenu' => ['19' => ['price' => 315.00, 'code2' => '3001'], '23' => ['price' => 473.00, 'code2' => '501'], '29' => ['price' => 599.00, 'code2' => '801'], '33' => ['price' => 725.00, 'code2' => '1201']],
        'pirog-s-indejkoj-gribami-surom-i-zelenu' => ['19' => ['price' => 315.00, 'code2' => '3002'], '23' => ['price' => 473.00, 'code2' => '502'], '29' => ['price' => 599.00, 'code2' => '802'], '33' => ['price' => 725.00, 'code2' => '1202']],
        'pirog-s-pomidorami-surom-i-zelenu' => ['19' => ['price' => 315.00, 'code2' => '3006'], '23' => ['price' => 473.00, 'code2' => '506'], '29' => ['price' => 599.00, 'code2' => '806'], '33' => ['price' => 725.00, 'code2' => '1206']],
        'pirog-s-surom-gribami-i-zelenu' => ['19' => ['price' => 315.00, 'code2' => '3007'], '23' => ['price' => 473.00, 'code2' => '507'], '29' => ['price' => 599.00, 'code2' => '807'], '33' => ['price' => 725.00, 'code2' => '1207']],
        'pirog-s-surom-i-shpinatom' => ['19' => ['price' => 294.00, 'code2' => '3013'], '23' => ['price' => 452.00, 'code2' => '513'], '29' => ['price' => 578.00, 'code2' => '813'], '33' => ['price' => 704.00, 'code2' => '1213']],
        'pirog-s-kartofelem-gribami-i-zelenu' => ['19' => ['price' => 263.00, 'code2' => '3016'], '23' => ['price' => 473.00, 'code2' => '516'], '29' => ['price' => 599.00, 'code2' => '816'], '33' => ['price' => 725.00, 'code2' => '1216']],
        'pirog-s-kurinum-file-surom-sladkim-i-ostrum-percem' => ['19' => ['price' => 315.00, 'code2' => '3017'], '23' => ['price' => 473.00, 'code2' => '517'], '29' => ['price' => 599.00, 'code2' => '817'], '33' => ['price' => 725.00, 'code2' => '1217']],
        'pirog-s-pomidorami-indejkoj-surom-i-zelenu' => ['19' => ['price' => 315.00, 'code2' => '3018'], '23' => ['price' => 473.00, 'code2' => '518'], '29' => ['price' => 599.00, 'code2' => '818'], '33' => ['price' => 725.00, 'code2' => '1218']],
        'pirog-s-indejkoj-vyalenumi-suhofruktami-surom-i-percem' => ['19' => ['price' => 315.00, 'code2' => '3021'], '23' => ['price' => 473.00, 'code2' => '521'], '29' => ['price' => 599.00, 'code2' => '821'], '33' => ['price' => 725.00, 'code2' => '1221']],
        'pirog-s-kapustoj-gribami-pomidorami-i-zelenu' => ['19' => ['price' => 263.00, 'code2' => '3020'], '23' => ['price' => 473.00, 'code2' => '520'], '29' => ['price' => 599.00, 'code2' => '820'], '33' => ['price' => 725.00, 'code2' => '1220']],
        'pirog-s-rublenoj-telyatinoj-i-zelenu' => ['19' => ['price' => 347.00, 'code2' => '3022'], '23' => ['price' => 504.00, 'code2' => '522'], '29' => ['price' => 599.00, 'code2' => '822'], '33' => ['price' => 756.00, 'code2' => '1222']],
        'pirog-s-baraninoj-telyatinoj-i-ostrum-percem' => ['19' => ['price' => 347.00, 'code2' => '3023'], '23' => ['price' => 504.00, 'code2' => '523'], '29' => ['price' => 599.00, 'code2' => '823'], '33' => ['price' => 725.00, 'code2' => '1223']],
        'pirog-s-makom-i-vishnej' => ['19' => ['price' => 263.00, 'code2' => '3027'], '23' => ['price' => 420.00, 'code2' => '527'], '29' => ['price' => 546.00, 'code2' => '827'], '33' => ['price' => 672.00, 'code2' => '1227']],
        'pirog-s-svininoj-pomidorami-zelenu-i-sladkim-percem' => ['19' => ['price' => 315.00, 'code2' => '3024'], '23' => ['price' => 473.00, 'code2' => '524'], '29' => ['price' => 599.00, 'code2' => '824'], '33' => ['price' => 725.00, 'code2' => '1224']],
        'pirog-s-tvorogom-izumom-i-cukatami' => ['19' => ['price' => 263.00, 'code2' => '3028'], '23' => ['price' => 420.00, 'code2' => '528'], '29' => ['price' => 546.00, 'code2' => '828'], '33' => ['price' => 672.00, 'code2' => '1228']],
        'pirig-z-yablukami-i-koriceu' => ['19' => ['price' => 263.00, 'code2' => '3035'], '23' => ['price' => 420.00, 'code2' => '530'], '29' => ['price' => 546.00, 'code2' => '830'], '33' => ['price' => 672.00, 'code2' => '1230']],
        'pirog-s-grushej-yablokami-i-mindalnumi-orehami' => ['19' => ['price' => 263.00, 'code2' => '3031'], '23' => ['price' => 420.00, 'code2' => '531'], '29' => ['price' => 546.00, 'code2' => '831'], '33' => ['price' => 672.00, 'code2' => '1231']],
        'pirog-s-klubnikoj-vishnej-i-yablokami' => ['19' => ['price' => 263.00, 'code2' => '3033'], '23' => ['price' => 420.00, 'code2' => '533'], '29' => ['price' => 546.00, 'code2' => '833'], '33' => ['price' => 672.00, 'code2' => '1233']],
        'pirog-s-semgoj-surom-pomidorami-i-zelenu' => ['19' => ['price' => 389.00, 'code2' => '3029'], '23' => ['price' => 546.00, 'code2' => '529'], '29' => ['price' => 672.00, 'code2' => '829'], '33' => ['price' => 798.00, 'code2' => '1229']],
        'pirog-s-tukvoj-i-surom' => ['19' => ['price' => 347.00, 'code2' => '3010'], '23' => ['price' => 504.00, 'code2' => '510'], '29' => ['price' => 630.00, 'code2' => '810'], '33' => ['price' => 756.00, 'code2' => '1210']],
        'pirog-s-belumi-gribami-surom-kartofelem-i-zelenu' => ['19' => ['price' => 164.99, 'code2' => '3015'], '23' => ['price' => 305.00, 'code2' => '515'], '29' => ['price' => 454.99, 'code2' => '815'], '33' => ['price' => 750.00, 'code2' => '1215']],
    ];

    private array $sizeCharacteristic = ['19' => 1, '23' => 2, '29' => 67, '33' => 3];
    private array $weightCharacteristic = ['19' => 68, '23' => 4, '29' => 5, '33' => 6];
    private array $personsCharacteristicDefault = ['19' => 179, '23' => 7, '29' => 8, '33' => 9];

    public function run(): void
    {
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            foreach ($this->matrix as $slug => $sizes) {
                $parent = DB::table('bs_products')
                    ->whereNull('parent_id')
                    ->where('slug', $slug)
                    ->first();

                if (! $parent) {
                    $skipped++;
                    continue;
                }

                $processed++;

                $parentSpec = $sizes['19'];
                DB::table('bs_products')
                    ->where('id', (int) $parent->id)
                    ->update([
                        'price' => $parentSpec['price'],
                        'code2' => $parentSpec['code2'],
                        'dop_info' => '19',
                        'updated_at' => now(),
                    ]);

                $this->syncCharacteristics((int) $parent->id, $slug, '19');
                $updated++;

                foreach (['23', '29', '33'] as $sizeKey) {
                    $spec = $sizes[$sizeKey];
                    $variant = DB::table('bs_products')
                        ->where('parent_id', (int) $parent->id)
                        ->where(function ($query) use ($parent, $sizeKey): void {
                            $query->where('dop_info', $sizeKey)
                                ->orWhere('slug', $parent->slug . '_' . $sizeKey);
                        })
                        ->orderBy('id')
                        ->first();

                    if (! $variant) {
                        $variantId = (int) DB::table('bs_products')->insertGetId([
                            'is_new' => 0,
                            'is_hit' => 0,
                            'is_home' => 0,
                            'code2' => $spec['code2'],
                            'is_imported' => (int) ($parent->is_imported ?? 0),
                            'import_source_id' => $parent->import_source_id,
                            'sort' => 0,
                            'parent_id' => (int) $parent->id,
                            'category_id' => $parent->category_id,
                            'sku' => null,
                            'title' => $parent->title,
                            'short_desc' => null,
                            'short_name' => $this->buildShortName((string) $parent->title, (string) ($parent->short_name ?? ''), $sizeKey),
                            'slug' => $this->uniqueSlug((string) $parent->slug . '_' . $sizeKey),
                            'main_image' => null,
                            'main_image_small' => null,
                            'description' => null,
                            'dop_info' => $sizeKey,
                            'price' => $spec['price'],
                            'old_price' => null,
                            'in_stock' => (int) ($parent->in_stock ?? 1),
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
                                'price' => $spec['price'],
                                'code2' => $spec['code2'],
                                'dop_info' => $sizeKey,
                                'updated_at' => now(),
                            ]);

                        $updated++;
                    }

                    $this->syncCharacteristics($variantId, $slug, $sizeKey);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("Classic pie variants restored. Processed parents: {$processed}, Created variants: {$created}, Updated records: {$updated}, Skipped missing parents: {$skipped}");
    }

    private function syncCharacteristics(int $productId, string $slug, string $sizeKey): void
    {
        $personsMap = $this->personsCharacteristicDefault;

        if ($slug === 'pirog-s-belumi-gribami-surom-kartofelem-i-zelenu' && $sizeKey === '23') {
            $personsMap['23'] = 8;
        }

        DB::table('bs_product_characteristic_value')
            ->where('product_id', $productId)
            ->whereIn('characteristic_id', [1, 2, 3, 21, 22, 23, 24])
            ->delete();

        $rows = [
            ['characteristic_id' => 1, 'characteristic_value_id' => $this->sizeCharacteristic[$sizeKey]],
            ['characteristic_id' => 2, 'characteristic_value_id' => $this->weightCharacteristic[$sizeKey]],
            ['characteristic_id' => 3, 'characteristic_value_id' => $personsMap[$sizeKey]],
        ];

        foreach ($rows as $row) {
            DB::table('bs_product_characteristic_value')->insert([
                'product_id' => $productId,
                'characteristic_id' => $row['characteristic_id'],
                'characteristic_value_id' => $row['characteristic_value_id'],
                'value_text' => null,
                'value_number' => null,
                'value_datetime' => null,
                'price_modifier' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $index = 2;

        while (DB::table('bs_products')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private function buildShortName(string $titleJson, string $fallbackShortName, string $size): string
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

        return $name . ' [' . $size . ']';
    }
}
