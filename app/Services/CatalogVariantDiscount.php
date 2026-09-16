<?php

namespace App\Services;

use App\Models\Shop\Product;
use Illuminate\Support\Facades\DB;

class CatalogVariantDiscount
{
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

    public function saveWithVariants(Product $parent, float $percent, callable $canEdit): void
    {
        DB::transaction(function () use ($parent, $percent, $canEdit): void {
            $parent->save();
            foreach ($parent->variants()->lockForUpdate()->get() as $variant) {
                if (! $canEdit($variant)) {
                    continue;
                }
                $values = $this->calculate(
                    (float) $variant->price,
                    $variant->old_price === null ? null : (float) $variant->old_price,
                    $variant->manual_discount_percent === null ? null : (float) $variant->manual_discount_percent,
                    $percent,
                );
                if ($values !== null) {
                    $variant->fill($values)->save();
                }
            }
        });
    }
}
