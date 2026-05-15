<?php

namespace Database\Seeders;

use App\Models\Shop\Product;
use Illuminate\Database\Seeder;

class BackfillVariantDisplaySortSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()
            ->whereNull('parent_id')
            ->whereHas('children')
            ->with(['children' => function ($query): void {
                $query->orderBy('sort')->orderBy('id');
            }])
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    $position = 10;

                    $product->variant_display_sort = $position;
                    $product->saveQuietly();

                    foreach ($product->children as $child) {
                        $position += 10;
                        $child->variant_display_sort = $position;
                        $child->saveQuietly();
                    }
                }
            });
    }
}
