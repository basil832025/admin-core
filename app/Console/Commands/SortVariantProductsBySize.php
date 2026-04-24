<?php

namespace App\Console\Commands;

use App\Models\Shop\Product;
use App\Models\Shop\ProductCharacteristicValue;
use Illuminate\Console\Command;

class SortVariantProductsBySize extends Command
{
    protected $signature = 'products:sort-variants-by-size {--slug=} {--dry-run}';

    protected $description = 'Sort child product variants by size characteristic and write sort values';

    public function handle(): int
    {
        $slug = trim((string) $this->option('slug'));
        $dryRun = (bool) $this->option('dry-run');

        $parentsQuery = Product::query()
            ->whereNull('parent_id')
            ->whereHas('children')
            ->with([
                'children' => fn ($q) => $q->orderBy('id'),
                'children.productCharacteristicValues.characteristic:id,slug',
                'children.productCharacteristicValues.characteristicValue:id,value',
            ]);

        if ($slug !== '') {
            $parentsQuery->where('slug', $slug);
        }

        $parents = $parentsQuery->get();

        if ($parents->isEmpty()) {
            $this->warn('No parent products found for sorting.');

            return self::SUCCESS;
        }

        $updatedParents = 0;
        $updatedChildren = 0;
        $skippedParents = 0;

        foreach ($parents as $parent) {
            $children = $parent->children;

            if ($children->count() < 2) {
                continue;
            }

            $prepared = [];
            $missingSize = false;

            foreach ($children as $child) {
                $size = $this->resolveSizeNumber($child);
                if ($size === null) {
                    $missingSize = true;
                    break;
                }

                $prepared[] = [
                    'model' => $child,
                    'size' => $size,
                ];
            }

            if ($missingSize) {
                $skippedParents++;
                $this->line("Skipped parent #{$parent->id} ({$parent->slug}): size missing for one of children.");
                continue;
            }

            usort($prepared, static function (array $a, array $b): int {
                $cmp = $a['size'] <=> $b['size'];
                if ($cmp !== 0) {
                    return $cmp;
                }

                return (int) $a['model']->id <=> (int) $b['model']->id;
            });

            $needsUpdate = false;
            foreach ($prepared as $index => $row) {
                $expectedSort = ($index + 1) * 10;
                if ((int) $row['model']->sort !== $expectedSort) {
                    $needsUpdate = true;
                    break;
                }
            }

            if (! $needsUpdate) {
                continue;
            }

            $updatedParents++;

            foreach ($prepared as $index => $row) {
                $expectedSort = ($index + 1) * 10;
                $child = $row['model'];

                $this->line(
                    sprintf(
                        'Parent #%d (%s): child #%d size=%s sort %d -> %d',
                        (int) $parent->id,
                        (string) $parent->slug,
                        (int) $child->id,
                        rtrim(rtrim(number_format((float) $row['size'], 2, '.', ''), '0'), '.'),
                        (int) $child->sort,
                        $expectedSort,
                    )
                );

                if (! $dryRun) {
                    $child->sort = $expectedSort;
                    $child->save();
                }

                $updatedChildren++;
            }
        }

        $mode = $dryRun ? 'DRY RUN' : 'APPLIED';
        $this->info("{$mode}: parents updated {$updatedParents}, children updated {$updatedChildren}, parents skipped {$skippedParents}.");

        return self::SUCCESS;
    }

    private function resolveSizeNumber(Product $product): ?float
    {
        $priority = ['rozmir-pirogiv', 'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];

        /** @var \Illuminate\Support\Collection<int, ProductCharacteristicValue> $rows */
        $rows = $product->productCharacteristicValues ?? collect();

        foreach ($priority as $slug) {
            $row = $rows->first(function (ProductCharacteristicValue $item) use ($slug): bool {
                $rowSlug = $item->characteristic?->slug
                    ?? $item->characteristicValue?->characteristic?->slug;

                return $rowSlug === $slug;
            });

            if (! $row) {
                continue;
            }

            $value = $this->extractRawValue($row);
            $number = $this->extractNumber($value);
            if ($number !== null) {
                return $number;
            }
        }

        $fallback = (string) ($product->sku ?: $product->code2 ?: '');

        return $this->extractNumber($fallback);
    }

    private function extractRawValue(ProductCharacteristicValue $row): string
    {
        if ($row->characteristicValue) {
            $value = $row->characteristicValue->value;
            if (is_array($value)) {
                return (string) ($value[app()->getLocale()] ?? $value['uk'] ?? $value['ru'] ?? $value['en'] ?? reset($value) ?? '');
            }

            return (string) $value;
        }

        if ($row->value_text !== null && $row->value_text !== '') {
            return (string) $row->value_text;
        }

        if ($row->value_number !== null) {
            return (string) $row->value_number;
        }

        return '';
    }

    private function extractNumber(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)/u', $value, $m) !== 1) {
            return null;
        }

        return (float) str_replace(',', '.', $m[1]);
    }
}
