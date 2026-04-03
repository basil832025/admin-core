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
            $snapshotPath = __DIR__ . '/dumps/ingredients_snapshot.php';
            if (is_file($snapshotPath)) {
                $this->syncFromSnapshot($snapshotPath);
                return;
            }

            $this->command?->error('Legacy tables catalog_consist_info / product_consist_assoc are missing.');
            $this->command?->error('Also no snapshot found: database/seeders/dumps/ingredients_snapshot.php');
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

    private function syncFromSnapshot(string $snapshotPath): void
    {
        $snapshot = require $snapshotPath;

        if (! is_array($snapshot)) {
            $this->command?->error('Invalid ingredients snapshot format.');
            return;
        }

        $ingredients = is_array($snapshot['ingredients'] ?? null) ? $snapshot['ingredients'] : [];
        $productIngredients = is_array($snapshot['product_ingredients'] ?? null) ? $snapshot['product_ingredients'] : [];

        if (empty($ingredients)) {
            $this->command?->error('Ingredients snapshot is empty.');
            return;
        }

        $upsertRows = [];
        foreach ($ingredients as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $nameRaw = $row['name'] ?? null;
            $nameJson = is_array($nameRaw)
                ? json_encode($nameRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string) $nameRaw;

            if ($nameJson === '' || $nameJson === 'null') {
                continue;
            }

            $upsertRows[] = [
                'id' => $id,
                'name' => $nameJson,
                'slug' => $row['slug'] ?? null,
                'is_active' => (int) ($row['is_active'] ?? 1),
                'legacy_consist_id' => isset($row['legacy_consist_id']) ? (int) $row['legacy_consist_id'] : null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        if (empty($upsertRows)) {
            $this->command?->error('No valid ingredient rows in snapshot.');
            return;
        }

        DB::beginTransaction();

        try {
            DB::table('bs_ingredients')->upsert(
                $upsertRows,
                ['id'],
                ['name', 'slug', 'is_active', 'legacy_consist_id', 'updated_at']
            );

            $productIds = [];
            $pivotRows = [];

            foreach ($productIngredients as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $productId = (int) ($row['product_id'] ?? 0);
                $ingredientId = (int) ($row['ingredient_id'] ?? 0);
                $sortOrder = (int) ($row['sort_order'] ?? 0);

                if ($productId <= 0 || $ingredientId <= 0) {
                    continue;
                }

                $productIds[$productId] = true;
                $pivotRows[] = [
                    'product_id' => $productId,
                    'ingredient_id' => $ingredientId,
                    'sort_order' => $sortOrder > 0 ? $sortOrder : 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($productIds)) {
                DB::table('bs_product_ingredient')
                    ->whereIn('product_id', array_keys($productIds))
                    ->delete();
            }

            if (! empty($pivotRows)) {
                DB::table('bs_product_ingredient')->insert($pivotRows);
            }

            DB::commit();

            $this->command?->info(
                'Ingredients synced from snapshot. Ingredients upserted: ' . count($upsertRows) . ', pivot rows inserted: ' . count($pivotRows)
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
