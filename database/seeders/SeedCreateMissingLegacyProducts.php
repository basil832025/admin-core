<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedCreateMissingLegacyProducts extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => 323,
                'slug' => 'uzvar-slivovuj',
                'category_id' => 7,
                'title' => '{"en":"Plum Uzvar","ru":"Узвар Сливовый","uk":"Узвар Сливовий"}',
                'description' => '{"en":"","ru":"","uk":""}',
                'sizes' => [
                    ['size' => '0.3', 'price' => 58.00, 'code2' => '167', 'chars' => [[21, 71]]],
                    ['size' => '0.5', 'price' => 79.00, 'code2' => '168', 'chars' => [[21, 72]]],
                    ['size' => '1', 'price' => 110.00, 'code2' => '169', 'chars' => [[21, 73]]],
                ],
            ],
            [
                'id' => 324,
                'slug' => 'kadurdzhin',
                'category_id' => 5,
                'title' => '{"en":"Kadurdzhyn","ru":"Кадурджин","uk":"Кадурджин"}',
                'description' => '{"en":"","ru":"","uk":""}',
                'sizes' => [
                    ['size' => '19', 'price' => 419.00, 'code2' => '100000507', 'chars' => [[1, 1], [2, 68], [3, 179]]],
                    ['size' => '23', 'price' => 629.00, 'code2' => '100000508', 'chars' => [[1, 2], [2, 4], [3, 7]]],
                    ['size' => '29', 'price' => 944.00, 'code2' => '00000509', 'chars' => [[1, 67], [2, 5], [3, 8]]],
                    ['size' => '33', 'price' => 1259.00, 'code2' => '00000510', 'chars' => [[1, 3], [2, 6], [3, 9]]],
                ],
            ],
            [
                'id' => 325,
                'slug' => 'pirog-s-krasnoj-fasolu-i-zelenu',
                'category_id' => 3,
                'title' => '{"en":"Red bean and herb pie","ru":"Пирог с красной фасолью и зеленью","uk":"Пиріг з червоною квасолею та зеленню"}',
                'description' => '{"en":"","ru":"","uk":""}',
                'sizes' => [
                    ['size' => '19', 'price' => 315.00, 'code2' => '100000511', 'chars' => [[1, 1], [2, 68], [3, 179]]],
                    ['size' => '23', 'price' => 473.00, 'code2' => '100000512', 'chars' => [[1, 2], [2, 4], [3, 7]]],
                    ['size' => '29', 'price' => 599.00, 'code2' => '100000513', 'chars' => [[1, 67], [2, 5], [3, 8]]],
                    ['size' => '33', 'price' => 725.00, 'code2' => '100000514', 'chars' => [[1, 3], [2, 6], [3, 9]]],
                ],
            ],
        ];

        $createdParents = 0;
        $createdVariants = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            foreach ($products as $spec) {
                $parent = DB::table('bs_products')
                    ->whereNull('parent_id')
                    ->where('slug', $spec['slug'])
                    ->first();

                $baseSize = $spec['sizes'][0];

                if (! $parent) {
                    $insert = [
                        'id' => $spec['id'],
                        'is_new' => 0,
                        'is_hit' => 0,
                        'is_home' => 0,
                        'code2' => $baseSize['code2'],
                        'is_imported' => 0,
                        'import_source_id' => null,
                        'sort' => 0,
                        'parent_id' => null,
                        'category_id' => $spec['category_id'],
                        'sku' => null,
                        'title' => $spec['title'],
                        'short_desc' => null,
                        'short_name' => null,
                        'slug' => $spec['slug'],
                        'main_image' => 'products/main/' . $spec['id'] . '.1.b.png',
                        'main_image_small' => 'products/small/' . $spec['id'] . '.1.s.jpg',
                        'description' => $spec['description'],
                        'dop_info' => $baseSize['size'],
                        'price' => $baseSize['price'],
                        'old_price' => null,
                        'in_stock' => 1,
                        'quantity' => 0,
                        'seo_title' => null,
                        'seo_description' => null,
                        'seo_keywords' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (DB::table('bs_products')->where('id', $spec['id'])->exists()) {
                        unset($insert['id']);
                    }

                    $parentId = (int) DB::table('bs_products')->insertGetId($insert);
                    $createdParents++;
                } else {
                    $parentId = (int) $parent->id;
                }

                DB::table('bs_products')
                    ->where('id', $parentId)
                    ->update([
                        'category_id' => $spec['category_id'],
                        'title' => $spec['title'],
                        'description' => $spec['description'],
                        'main_image' => 'products/main/' . $spec['id'] . '.1.b.png',
                        'main_image_small' => 'products/small/' . $spec['id'] . '.1.s.jpg',
                        'price' => $baseSize['price'],
                        'code2' => $baseSize['code2'],
                        'dop_info' => $baseSize['size'],
                        'updated_at' => now(),
                    ]);

                $this->syncCharacteristics($parentId, $baseSize['chars']);
                $updated++;

                foreach (array_slice($spec['sizes'], 1) as $sizeSpec) {
                    $sizeSlug = str_replace('.', '-', $sizeSpec['size']);

                    $variant = DB::table('bs_products')
                        ->where('parent_id', $parentId)
                        ->where(function ($query) use ($spec, $sizeSpec, $sizeSlug): void {
                            $query->where('dop_info', $sizeSpec['size'])
                                ->orWhere('slug', $spec['slug'] . '_' . $sizeSlug);
                        })
                        ->orderBy('id')
                        ->first();

                    if (! $variant) {
                        DB::table('bs_products')->insert([
                            'is_new' => 0,
                            'is_hit' => 0,
                            'is_home' => 0,
                            'code2' => $sizeSpec['code2'],
                            'is_imported' => 0,
                            'import_source_id' => null,
                            'sort' => 0,
                            'parent_id' => $parentId,
                            'category_id' => $spec['category_id'],
                            'sku' => null,
                            'title' => $spec['title'],
                            'short_desc' => null,
                            'short_name' => null,
                            'slug' => $this->uniqueSlug($spec['slug'] . '_' . $sizeSlug),
                            'main_image' => null,
                            'main_image_small' => null,
                            'description' => null,
                            'dop_info' => $sizeSpec['size'],
                            'price' => $sizeSpec['price'],
                            'old_price' => null,
                            'in_stock' => 1,
                            'quantity' => 0,
                            'seo_title' => null,
                            'seo_description' => null,
                            'seo_keywords' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $variantId = (int) DB::getPdo()->lastInsertId();
                        $createdVariants++;
                    } else {
                        $variantId = (int) $variant->id;

                        DB::table('bs_products')
                            ->where('id', $variantId)
                            ->update([
                                'price' => $sizeSpec['price'],
                                'code2' => $sizeSpec['code2'],
                                'dop_info' => $sizeSpec['size'],
                                'updated_at' => now(),
                            ]);

                        $updated++;
                    }

                    $this->syncCharacteristics($variantId, $sizeSpec['chars']);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info("Local missing products sync done. Created parents: {$createdParents}, Created variants: {$createdVariants}, Updated records: {$updated}");
    }

    private function syncCharacteristics(int $productId, array $pairs): void
    {
        DB::table('bs_product_characteristic_value')
            ->where('product_id', $productId)
            ->whereIn('characteristic_id', [1, 2, 3, 21, 22, 23, 24])
            ->delete();

        foreach ($pairs as [$characteristicId, $valueId]) {
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
}
