<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedMigrateLegacyConsistToIngredients extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('bs_ingredients') || ! Schema::hasTable('bs_product_ingredient')) {
            $this->command?->error('Run migrations first: bs_ingredients / bs_product_ingredient tables are missing.');
            return;
        }

        if (! Schema::hasTable('catalog_consist_info') || ! Schema::hasTable('product_consist_assoc')) {
            $this->command?->error('Legacy tables catalog_consist_info / product_consist_assoc are missing.');
            return;
        }

        $legacyConsists = DB::table('catalog_consist_info')
            ->select(['record_id', 'lang', 'title'])
            ->whereNotNull('title')
            ->orderBy('record_id')
            ->get();

        $grouped = [];
        foreach ($legacyConsists as $row) {
            $legacyId = (int) $row->record_id;
            $lang = (string) $row->lang;
            $title = trim((string) $row->title);
            if ($legacyId <= 0 || $title === '') {
                continue;
            }

            if (! isset($grouped[$legacyId])) {
                $grouped[$legacyId] = ['uk' => '', 'ru' => '', 'en' => ''];
            }

            if ($lang === '1') {
                $grouped[$legacyId]['ru'] = $title;
            } elseif ($lang === '2') {
                $grouped[$legacyId]['uk'] = $title;
            } elseif ($lang === '3') {
                $grouped[$legacyId]['en'] = $title;
            }
        }

        $createdIngredients = 0;
        $updatedIngredients = 0;

        DB::beginTransaction();

        try {
            foreach ($grouped as $legacyId => $nameMap) {
                if ($nameMap['uk'] === '' && $nameMap['ru'] !== '') {
                    $nameMap['uk'] = $nameMap['ru'];
                }
                if ($nameMap['ru'] === '' && $nameMap['uk'] !== '') {
                    $nameMap['ru'] = $nameMap['uk'];
                }
                if ($nameMap['en'] === '') {
                    $nameMap['en'] = $nameMap['uk'] !== '' ? $nameMap['uk'] : $nameMap['ru'];
                }

                $baseName = $nameMap['uk'] !== '' ? $nameMap['uk'] : ($nameMap['ru'] !== '' ? $nameMap['ru'] : $nameMap['en']);
                $baseSlug = Str::slug($baseName);
                if ($baseSlug === '') {
                    $baseSlug = 'ingredient-' . $legacyId;
                }

                $ingredient = DB::table('bs_ingredients')
                    ->where('legacy_consist_id', $legacyId)
                    ->first();

                if (! $ingredient) {
                    $slug = $this->uniqueSlug($baseSlug);
                    DB::table('bs_ingredients')->insert([
                        'name' => json_encode($nameMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'slug' => $slug,
                        'is_active' => 1,
                        'legacy_consist_id' => $legacyId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $createdIngredients++;
                } else {
                    DB::table('bs_ingredients')
                        ->where('id', (int) $ingredient->id)
                        ->update([
                            'name' => json_encode($nameMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'is_active' => 1,
                            'updated_at' => now(),
                        ]);
                    $updatedIngredients++;
                }
            }

            $legacyMap = DB::table('bs_ingredients')
                ->whereNotNull('legacy_consist_id')
                ->pluck('id', 'legacy_consist_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $assocRows = DB::table('product_consist_assoc')
                ->select(['product_id', 'consist_id'])
                ->orderBy('product_id')
                ->orderBy('consist_id')
                ->get();

            $byProduct = [];
            foreach ($assocRows as $row) {
                $productId = (int) $row->product_id;
                $legacyConsistId = (int) $row->consist_id;
                if ($productId <= 0 || $legacyConsistId <= 0) {
                    continue;
                }
                if (! isset($legacyMap[$legacyConsistId])) {
                    continue;
                }
                $ingredientId = $legacyMap[$legacyConsistId];

                if (! isset($byProduct[$productId])) {
                    $byProduct[$productId] = [];
                }
                if (! in_array($ingredientId, $byProduct[$productId], true)) {
                    $byProduct[$productId][] = $ingredientId;
                }
            }

            $existingProducts = DB::table('bs_products')->pluck('id')->map(fn ($v) => (int) $v)->all();
            $existingSet = array_fill_keys($existingProducts, true);

            $syncedProducts = 0;
            $insertedPivots = 0;

            foreach ($byProduct as $productId => $ingredientIds) {
                if (! isset($existingSet[$productId])) {
                    continue;
                }

                DB::table('bs_product_ingredient')
                    ->where('product_id', $productId)
                    ->delete();

                $rowsToInsert = [];
                foreach (array_values($ingredientIds) as $idx => $ingredientId) {
                    $rowsToInsert[] = [
                        'product_id' => $productId,
                        'ingredient_id' => $ingredientId,
                        'sort_order' => $idx + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (! empty($rowsToInsert)) {
                    DB::table('bs_product_ingredient')->insert($rowsToInsert);
                    $syncedProducts++;
                    $insertedPivots += count($rowsToInsert);
                }
            }

            DB::commit();

            $this->command?->info(
                "Ingredient migration done. Ingredients created: {$createdIngredients}, updated: {$updatedIngredients}, products synced: {$syncedProducts}, pivot rows inserted: {$insertedPivots}"
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $i = 2;

        while (DB::table('bs_ingredients')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
