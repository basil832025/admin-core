<?php

namespace App\Services;

use App\Models\Shop\Product;
use App\Models\Shop\ProductPriceChangeBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProductBulkPriceService
{
    public const SCOPE_SELECTED = 'selected';

    public const SCOPE_CATEGORIES = 'categories';

    public const SCOPE_ALL = 'all';

    public const SCOPE_FILTERED = 'filtered';

    public function __construct(private readonly ProductPriceCalculator $calculator) {}

    public static function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('bulk_update_product_prices');
    }

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_CATEGORIES => 'Одна або кілька категорій',
            self::SCOPE_ALL => 'Усі товари каталогу',
            self::SCOPE_FILTERED => 'Усі товари за поточними фільтрами та пошуком',
        ];
    }

    public static function scopeLabel(string $scope): string
    {
        return [
            self::SCOPE_SELECTED => 'Вибрані товари',
            self::SCOPE_CATEGORIES => 'Категорії',
            self::SCOPE_ALL => 'Усі товари',
            self::SCOPE_FILTERED => 'Поточна фільтрація',
        ][$scope] ?? $scope;
    }

    public function baseCatalogQuery(): Builder
    {
        return Product::query()
            ->parents()
            ->where(function (Builder $query): void {
                $query->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            });
    }

    public function idsForSelected(array $selectedIds, bool $includeVariants): array
    {
        $query = $this->baseCatalogQuery()->whereKey($this->normalizeIds($selectedIds));

        return $this->idsFromQuery($query, $includeVariants);
    }

    public function idsForCategories(array $categoryIds, bool $includeVariants): array
    {
        $categoryIds = $this->normalizeIds($categoryIds);

        if ($categoryIds === []) {
            return [];
        }

        $query = $this->baseCatalogQuery()
            ->where(function (Builder $query) use ($categoryIds): void {
                $query->whereIn('category_id', $categoryIds)
                    ->orWhereHas('categories', function (Builder $categories) use ($categoryIds): void {
                        $categories->whereIn('bs_product_categories.id', $categoryIds);
                    });
            });

        return $this->idsFromQuery($query, $includeVariants);
    }

    public function idsForAll(bool $includeVariants): array
    {
        return $this->idsFromQuery($this->baseCatalogQuery(), $includeVariants);
    }

    public function idsFromQuery(Builder $query, bool $includeVariants): array
    {
        $parents = (clone $query)
            ->reorder()
            ->select('bs_products.id')
            ->distinct()
            ->pluck('bs_products.id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if (! $includeVariants || $parents->isEmpty()) {
            return $parents->all();
        }

        $variants = Product::query()
            ->whereIn('parent_id', $parents->all())
            ->where(function (Builder $query): void {
                $query->whereNull('is_imported')
                    ->orWhere('is_imported', false);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        return $parents
            ->merge($variants)
            ->unique()
            ->values()
            ->all();
    }

    public function preview(array $productIds, array $data, int $limit = 5): array
    {
        $ids = $this->normalizeIds($productIds);
        $operation = (string) ($data['operation'] ?? '');
        $value = (float) ($data['value'] ?? 0);
        $oldPriceMode = (string) ($data['old_price_mode'] ?? '');
        $roundingPrecision = (int) ($data['rounding_precision'] ?? 0);

        $this->calculator->assertValid($operation, $value, $oldPriceMode, $roundingPrecision);

        $sampleIds = array_slice($ids, 0, $limit);
        $products = Product::query()
            ->whereKey($sampleIds)
            ->with('parent:id,title,short_name')
            ->get()
            ->keyBy('id');

        $rows = collect($sampleIds)
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter()
            ->map(function (Product $product) use ($operation, $value, $oldPriceMode, $roundingPrecision): array {
                $beforePrice = (float) $product->price;
                $beforeOldPrice = $product->old_price !== null ? (float) $product->old_price : null;
                $after = $this->calculator->calculate(
                    $beforePrice,
                    $beforeOldPrice,
                    $product->manual_discount_percent !== null ? (float) $product->manual_discount_percent : null,
                    $operation,
                    $value,
                    $oldPriceMode,
                    $roundingPrecision,
                );

                return [
                    'id' => $product->id,
                    'label' => $product->adminLabel(),
                    'old_price_value' => $beforePrice,
                    'new_price_value' => $after['price'],
                    'old_old_price' => $beforeOldPrice,
                    'new_old_price' => $after['old_price'],
                    'old_discount' => $this->roundedDiscountPercent(
                        $this->calculator->discountPercent($beforePrice, $beforeOldPrice)
                    ),
                    'new_discount' => $this->roundedDiscountPercent($after['manual_discount_percent']),
                ];
            })
            ->all();

        return ['count' => count($ids), 'rows' => $rows];
    }

    public function apply(array $productIds, array $data, ?User $user, array $metadata = []): ProductPriceChangeBatch
    {
        $ids = $this->normalizeIds($productIds);

        if ($ids === []) {
            throw new RuntimeException('Не знайдено товарів для зміни ціни.');
        }

        $operation = (string) ($data['operation'] ?? '');
        $value = (float) ($data['value'] ?? 0);
        $oldPriceMode = (string) ($data['old_price_mode'] ?? '');
        $roundingPrecision = (int) ($data['rounding_precision'] ?? 0);
        $this->calculator->assertValid($operation, $value, $oldPriceMode, $roundingPrecision);

        $batch = ProductPriceChangeBatch::create([
            'user_id' => $user?->id,
            'scope' => (string) ($metadata['scope'] ?? self::SCOPE_SELECTED),
            'operation' => $operation,
            'value' => $value,
            'old_price_mode' => $oldPriceMode,
            'rounding_precision' => $roundingPrecision,
            'include_variants' => (bool) ($data['include_variants'] ?? true),
            'category_ids' => $metadata['category_ids'] ?? null,
            'filters' => $metadata['filters'] ?? null,
            'status' => 'processing',
        ]);

        try {
            DB::transaction(function () use ($batch, $ids, $operation, $value, $oldPriceMode, $roundingPrecision): void {
                $now = now();
                $affectedCount = 0;

                Product::query()
                    ->whereKey($ids)
                    ->with('parent:id,title,short_name')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->chunkById(250, function ($products) use (
                        $batch,
                        $operation,
                        $value,
                        $oldPriceMode,
                        $roundingPrecision,
                        $now,
                        &$affectedCount,
                    ): void {
                        $items = [];

                        foreach ($products as $product) {
                            $beforePrice = (float) $product->price;
                            $beforeOldPrice = $product->old_price !== null ? (float) $product->old_price : null;
                            $beforeDiscount = $product->manual_discount_percent !== null
                                ? (float) $product->manual_discount_percent
                                : $this->calculator->discountPercent($beforePrice, $beforeOldPrice);
                            $after = $this->calculator->calculate(
                                $beforePrice,
                                $beforeOldPrice,
                                $beforeDiscount,
                                $operation,
                                $value,
                                $oldPriceMode,
                                $roundingPrecision,
                            );

                            $items[] = [
                                'batch_id' => $batch->id,
                                'product_id' => $product->id,
                                'product_label' => $product->adminLabel(),
                                'old_price' => $beforePrice,
                                'new_price' => $after['price'],
                                'old_old_price' => $beforeOldPrice,
                                'new_old_price' => $after['old_price'],
                                'old_discount_percent' => $beforeDiscount,
                                'new_discount_percent' => $this->roundedDiscountPercent($after['manual_discount_percent']),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];

                            Product::query()->whereKey($product->id)->update([
                                'price' => $after['price'],
                                'old_price' => $after['old_price'],
                                'manual_discount_percent' => $this->roundedDiscountPercent($after['manual_discount_percent']),
                                'updated_at' => $now,
                            ]);
                        }

                        if ($items !== []) {
                            DB::table('bs_product_price_change_items')->insert($items);
                            $affectedCount += count($items);
                        }
                    }, 'id');

                $batch->update([
                    'affected_count' => $affectedCount,
                    'status' => 'completed',
                    'error' => null,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $batch->forceFill([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }

        $this->bumpCatalogCache();

        return $batch->refresh();
    }

    public function revert(ProductPriceChangeBatch $batch, ?User $user): ProductPriceChangeBatch
    {
        DB::transaction(function () use ($batch, $user): void {
            $batch = ProductPriceChangeBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if ($batch->status !== 'completed' || $batch->reverted_at !== null) {
                throw new RuntimeException('Цю операцію вже скасовано або її неможливо скасувати.');
            }

            $items = $batch->items()->orderBy('id')->get();
            $now = now();

            foreach ($items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                Product::query()->whereKey($item->product_id)->update([
                    'price' => $item->old_price,
                    'old_price' => $item->old_old_price,
                    'manual_discount_percent' => $item->old_discount_percent,
                    'updated_at' => $now,
                ]);
            }

            $batch->update([
                'status' => 'reverted',
                'reverted_by_user_id' => $user?->id,
                'reverted_at' => $now,
            ]);
        }, 3);

        $this->bumpCatalogCache();

        return $batch->refresh();
    }

    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function bumpCatalogCache(): void
    {
        try {
            app(CatalogCacheService::class)->bump();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function roundedDiscountPercent(?float $value): ?float
    {
        return $value === null ? null : round($value, 0);
    }
}
