<?php

namespace App\Services;

use App\Models\Shop\Product;
use Illuminate\Support\Facades\DB;

class CatalogVariantDiscount
{
    public function discountPercent(float $price, ?float $oldPrice, ?float $manualPercent): ?float
    {
        if ($manualPercent !== null && $manualPercent > 0) {
            return round($manualPercent);
        }

        if ($oldPrice === null || $oldPrice <= 0 || $price <= 0 || $oldPrice <= $price) {
            return null;
        }

        return round((($oldPrice - $price) / $oldPrice) * 100);
    }

    public function calculate(float $price, ?float $oldPrice, ?float $manualPercent, float $percent): ?array
    {
        if ($price <= 0 || $percent <= 0 || $manualPercent > 0 || $oldPrice > $price) {
            return null;
        }

        return [
            'old_price' => round($price),
            'manual_discount_percent' => round($percent),
            'price' => round($price * (1 - $percent / 100)),
        ];
    }

    public function valuesForFollowingVariant(float $price, ?float $oldPrice, ?float $newPercent): ?array
    {
        $basePrice = $oldPrice !== null && $oldPrice > 0 && $oldPrice > $price
            ? $oldPrice
            : $price;

        if ($basePrice <= 0) {
            return null;
        }

        if ($newPercent === null || $newPercent <= 0) {
            return [
                'old_price' => null,
                'manual_discount_percent' => null,
                'price' => round($basePrice),
            ];
        }

        return [
            'old_price' => round($basePrice),
            'manual_discount_percent' => round($newPercent),
            'price' => round($basePrice * (1 - $newPercent / 100)),
        ];
    }

    public function saveWithVariants(
        Product $parent,
        ?float $previousPercent,
        ?float $newPercent,
        callable $canEdit,
    ): void
    {
        DB::transaction(function () use ($parent, $previousPercent, $newPercent, $canEdit): void {
            $parent->save();
            foreach ($parent->variants()->lockForUpdate()->get() as $variant) {
                if (! $canEdit($variant)) {
                    continue;
                }

                $variantPercent = $this->discountPercent(
                    (float) $variant->price,
                    $variant->old_price === null ? null : (float) $variant->old_price,
                    $variant->manual_discount_percent === null ? null : (float) $variant->manual_discount_percent,
                );

                if ($variantPercent !== $previousPercent) {
                    continue;
                }

                $values = $this->valuesForFollowingVariant(
                    (float) $variant->price,
                    $variant->old_price === null ? null : (float) $variant->old_price,
                    $newPercent,
                );
                if ($values !== null) {
                    $variant->fill($values)->save();
                }
            }
        });
    }
}
