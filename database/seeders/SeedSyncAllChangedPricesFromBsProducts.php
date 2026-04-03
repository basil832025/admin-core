<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeedSyncAllChangedPricesFromBsProducts extends Seeder
{
    public function run(): void
    {
        $sourceRows = DB::table('bs_cc_source_products')
            ->select(['source_id', 'external_parent_id', 'alias', 'size_label', 'price'])
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->get();

        $sourceByParentId = [];
        $sourceByAlias = [];

        foreach ($sourceRows as $row) {
            $variant = [
                'source_id' => (int) $row->source_id,
                'size_key' => $this->normalizeSizeKey((string) ($row->size_label ?? '')),
                'price' => (float) $row->price,
            ];

            $externalParentId = trim((string) ($row->external_parent_id ?? ''));
            if ($externalParentId !== '' && ctype_digit($externalParentId)) {
                $pid = (int) $externalParentId;
                $sourceByParentId[$pid][$variant['source_id']][] = $variant;
            }

            $alias = trim((string) ($row->alias ?? ''));
            if ($alias !== '') {
                $sourceByAlias[$alias][$variant['source_id']][] = $variant;
            }
        }

        $parents = DB::table('bs_products')
            ->whereNull('parent_id')
            ->where(function ($query): void {
                $query->whereNull('is_imported')->orWhere('is_imported', 0);
            })
            ->select(['id', 'slug'])
            ->get();

        $sourcePriority = [1, 7, 2, 3];

        $matchedParents = 0;
        $updatedParents = 0;
        $checkedVariants = 0;
        $updatedVariants = 0;
        $missingSizeInFamily = 0;
        $unmappedParents = 0;
        $unmappedSamples = [];

        DB::beginTransaction();

        try {
            foreach ($parents as $parent) {
                $parentId = (int) $parent->id;
                $slug = (string) $parent->slug;

                $familyProducts = DB::table('bs_products')
                    ->where('id', $parentId)
                    ->orWhere('parent_id', $parentId)
                    ->select(['id', 'price', 'dop_info'])
                    ->get();

                if ($familyProducts->isEmpty()) {
                    continue;
                }

                $familyBySize = [];
                foreach ($familyProducts as $familyProduct) {
                    $sizeKey = $this->normalizeSizeKey((string) ($familyProduct->dop_info ?? ''));
                    if ($sizeKey !== null && ! isset($familyBySize[$sizeKey])) {
                        $familyBySize[$sizeKey] = (int) $familyProduct->id;
                    }
                }

                if (empty($familyBySize) && $familyProducts->count() === 1) {
                    $familyBySize['__single'] = (int) $familyProducts->first()->id;
                }

                $candidatesBySource = [];
                if (isset($sourceByParentId[$parentId])) {
                    foreach ($sourceByParentId[$parentId] as $sourceId => $variants) {
                        $candidatesBySource[$sourceId] = array_merge($candidatesBySource[$sourceId] ?? [], $variants);
                    }
                }

                if ($slug !== '' && isset($sourceByAlias[$slug])) {
                    foreach ($sourceByAlias[$slug] as $sourceId => $variants) {
                        $candidatesBySource[$sourceId] = array_merge($candidatesBySource[$sourceId] ?? [], $variants);
                    }
                }

                if (empty($candidatesBySource)) {
                    $unmappedParents++;
                    if (count($unmappedSamples) < 20) {
                        $unmappedSamples[] = $slug;
                    }
                    continue;
                }

                $matchedParents++;

                $selectedSourceId = $this->pickBestSourceId($candidatesBySource, array_keys($familyBySize), $sourcePriority);
                $sourceVariants = $this->dedupeBySize($candidatesBySource[$selectedSourceId]);

                $parentChanged = false;
                foreach ($sourceVariants as $sourceVariant) {
                    $sizeKey = $sourceVariant['size_key'] ?? null;
                    $price = (float) $sourceVariant['price'];

                    if ($sizeKey === null) {
                        if (count($familyBySize) === 1 && isset($familyBySize['__single'])) {
                            $sizeKey = '__single';
                        } else {
                            continue;
                        }
                    }

                    if (! isset($familyBySize[$sizeKey])) {
                        $missingSizeInFamily++;
                        continue;
                    }

                    $checkedVariants++;
                    $productId = $familyBySize[$sizeKey];
                    $currentPrice = (float) DB::table('bs_products')->where('id', $productId)->value('price');
                    if (abs($currentPrice - $price) <= 0.009) {
                        continue;
                    }

                    DB::table('bs_products')
                        ->where('id', $productId)
                        ->update([
                            'price' => $price,
                            'updated_at' => now(),
                        ]);

                    $updatedVariants++;
                    $parentChanged = true;
                }

                if ($parentChanged) {
                    $updatedParents++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->command?->info(
            "All mapped bs_products prices synced. Matched parents: {$matchedParents}, Updated parents: {$updatedParents}, Checked variants: {$checkedVariants}, Updated variants: {$updatedVariants}, Source sizes not present in family: {$missingSizeInFamily}, Unmapped parents: {$unmappedParents}"
        );

        if (! empty($unmappedSamples)) {
            $this->command?->warn('Unmapped parent slug samples: ' . implode(', ', $unmappedSamples));
        }
    }

    private function dedupeBySize(array $variants): array
    {
        $result = [];
        foreach ($variants as $variant) {
            $key = $variant['size_key'] ?? '__single';
            if (! isset($result[$key])) {
                $result[$key] = $variant;
            }
        }

        return array_values($result);
    }

    private function pickBestSourceId(array $candidatesBySource, array $familyKeys, array $priority): int
    {
        $scores = [];

        foreach ($candidatesBySource as $sourceId => $variants) {
            $seen = [];
            foreach ($variants as $variant) {
                $sizeKey = $variant['size_key'] ?? '__single';
                $seen[$sizeKey] = true;
            }

            $matchCount = 0;
            foreach (array_keys($seen) as $sizeKey) {
                if (in_array($sizeKey, $familyKeys, true)) {
                    $matchCount++;
                }
            }

            $scores[(int) $sourceId] = [
                'match' => $matchCount,
                'total' => count($seen),
                'priority' => $this->sourcePriorityIndex((int) $sourceId, $priority),
            ];
        }

        uasort($scores, static function (array $a, array $b): int {
            if ($a['match'] !== $b['match']) {
                return $b['match'] <=> $a['match'];
            }

            if ($a['total'] !== $b['total']) {
                return $b['total'] <=> $a['total'];
            }

            return $a['priority'] <=> $b['priority'];
        });

        return (int) array_key_first($scores);
    }

    private function sourcePriorityIndex(int $sourceId, array $priority): int
    {
        $idx = array_search($sourceId, $priority, true);
        if ($idx === false) {
            return 999 + $sourceId;
        }

        return (int) $idx;
    }

    private function normalizeSizeKey(string $raw): ?string
    {
        $value = str_replace(',', '.', trim($raw));
        if ($value === '') {
            return null;
        }

        if (preg_match('/\d+(?:\.\d+)?/', $value, $matches) !== 1) {
            return null;
        }

        $num = (float) $matches[0];
        if (abs($num - round($num)) < 0.00001) {
            return (string) (int) round($num);
        }

        return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
    }
}
