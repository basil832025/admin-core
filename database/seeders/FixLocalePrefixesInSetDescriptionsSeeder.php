<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixLocalePrefixesInSetDescriptionsSeeder extends Seeder
{
    private const CATEGORY_SLUGS = ['tasting-sets', 'kombo-nabor'];

    public function run(): void
    {
        $categoryIds = $this->collectCategoryIds(self::CATEGORY_SLUGS);

        if (empty($categoryIds)) {
            $this->command?->warn('Categories not found: tasting-sets, kombo-nabor');
            return;
        }

        $productIds = $this->collectProductIds($categoryIds);

        if (empty($productIds)) {
            $this->command?->warn('No products found in target category groups.');
            return;
        }

        $checked = 0;
        $updated = 0;
        $fixedLinks = 0;

        DB::table('bs_products')
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->chunkById(200, function ($products) use (&$checked, &$updated, &$fixedLinks): void {
                foreach ($products as $product) {
                    $checked++;

                    $descriptionRaw = $product->description;
                    $shortDescRaw = $product->short_desc;

                    $descriptionData = $this->decodeJsonObject($descriptionRaw);
                    $descriptionChanged = false;
                    $shortDescChanged = false;
                    $rowFixedLinks = 0;

                    if (is_array($descriptionData)) {
                        foreach ($descriptionData as $locale => $value) {
                            if (! is_string($value) || $value === '') {
                                continue;
                            }

                            [$newValue, $count] = $this->normalizeLocalePrefixesInHtml($value);
                            if ($count > 0) {
                                $descriptionData[$locale] = $newValue;
                                $descriptionChanged = true;
                                $rowFixedLinks += $count;
                            }
                        }
                    }

                    if (is_string($shortDescRaw) && $shortDescRaw !== '') {
                        [$newShortDesc, $count] = $this->normalizeLocalePrefixesInHtml($shortDescRaw);
                        if ($count > 0) {
                            $shortDescRaw = $newShortDesc;
                            $shortDescChanged = true;
                            $rowFixedLinks += $count;
                        }
                    }

                    if (! $descriptionChanged && ! $shortDescChanged) {
                        continue;
                    }

                    $payload = ['updated_at' => now()];

                    if ($descriptionChanged) {
                        $payload['description'] = json_encode($descriptionData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }

                    if ($shortDescChanged) {
                        $payload['short_desc'] = $shortDescRaw;
                    }

                    DB::table('bs_products')
                        ->where('id', (int) $product->id)
                        ->update($payload);

                    $updated++;
                    $fixedLinks += $rowFixedLinks;
                }
            });

        $this->command?->info("Checked products: {$checked}");
        $this->command?->info("Updated products: {$updated}");
        $this->command?->info("Fixed links: {$fixedLinks}");
    }

    /**
     * @return array<int>
     */
    private function collectCategoryIds(array $rootSlugs): array
    {
        $rootIds = DB::table('bs_product_categories')
            ->whereIn('slug', $rootSlugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($rootIds)) {
            return [];
        }

        $allIds = array_fill_keys($rootIds, true);
        $queue = $rootIds;

        while (! empty($queue)) {
            $children = DB::table('bs_product_categories')
                ->whereIn('parent_id', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $queue = [];
            foreach ($children as $childId) {
                if (! isset($allIds[$childId])) {
                    $allIds[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        return array_map('intval', array_keys($allIds));
    }

    /**
     * @param array<int> $categoryIds
     * @return array<int>
     */
    private function collectProductIds(array $categoryIds): array
    {
        $byMainCategory = DB::table('bs_products')
            ->whereIn('category_id', $categoryIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $byPivot = DB::table('bs_product_product_category')
            ->whereIn('product_category_id', $categoryIds)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $merged = array_unique(array_merge($byMainCategory, $byPivot));
        sort($merged);

        return $merged;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function normalizeLocalePrefixesInHtml(string $html): array
    {
        $count = 0;

        $normalized = preg_replace_callback(
            '/href\s*=\s*(["\'])(.*?)\1/iu',
            static function (array $matches) use (&$count): string {
                $quote = $matches[1];
                $href = $matches[2];

                $newHref = preg_replace('/^\/(?:ru|uk|en)(?=\/|$)/iu', '', $href, 1, $pathCount);

                if (($pathCount ?? 0) === 0) {
                    $newHref = preg_replace('/^(https?:\/\/[^\/]+)\/(?:ru|uk|en)(\/|$)/iu', '$1$2', $href, 1, $absCount);
                    $pathCount = $absCount ?? 0;
                }

                if (($pathCount ?? 0) > 0 && is_string($newHref) && $newHref !== '') {
                    if (str_starts_with($newHref, '//')) {
                        $newHref = '/' . ltrim($newHref, '/');
                    }

                    if (! preg_match('/^(?:\/|#|https?:\/\/|mailto:|tel:|javascript:)/iu', $newHref)) {
                        $newHref = '/' . ltrim($newHref, '/');
                    }

                    $count += (int) $pathCount;
                    return 'href=' . $quote . $newHref . $quote;
                }

                if (
                    is_string($href)
                    && str_starts_with($href, '//')
                    && ! preg_match('/^\/\/[a-z0-9.-]+\.[a-z]{2,}(?:\/|$)/iu', $href)
                ) {
                    $count++;
                    return 'href=' . $quote . '/' . ltrim($href, '/') . $quote;
                }

                if (
                    is_string($href)
                    && $href !== ''
                    && ! preg_match('/^(?:\/|#|https?:\/\/|mailto:|tel:|javascript:)/iu', $href)
                    && preg_match('/^[\p{L}\p{N}]/u', $href)
                ) {
                    $count++;
                    return 'href=' . $quote . '/' . ltrim($href, '/') . $quote;
                }

                return $matches[0];
            },
            $html
        );

        return [is_string($normalized) ? $normalized : $html, $count];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
