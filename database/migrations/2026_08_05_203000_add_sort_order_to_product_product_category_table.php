<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bs_product_product_category', 'sort_order')) {
            Schema::table('bs_product_product_category', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->nullable()->after('product_category_id');
                $table->index('sort_order', 'ppc_sort_order_index');
            });
        }

        DB::table('bs_products')
            ->whereNotNull('category_id')
            ->orderBy('id')
            ->select(['id', 'category_id'])
            ->chunkById(500, function ($products): void {
                foreach ($products as $product) {
                    DB::table('bs_product_product_category')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'product_category_id' => $product->category_id,
                        ],
                        []
                    );
                }
            });

        $rows = DB::table('bs_product_product_category as ppc')
            ->join('bs_products as p', 'p.id', '=', 'ppc.product_id')
            ->orderBy('ppc.product_category_id')
            ->orderBy('p.sort')
            ->orderBy('p.id')
            ->get([
                'ppc.product_id',
                'ppc.product_category_id',
            ])
            ->groupBy('product_category_id');

        foreach ($rows as $categoryRows) {
            foreach ($categoryRows->values() as $index => $row) {
                DB::table('bs_product_product_category')
                    ->where('product_id', $row->product_id)
                    ->where('product_category_id', $row->product_category_id)
                    ->update(['sort_order' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bs_product_product_category', 'sort_order')) {
            return;
        }

        Schema::table('bs_product_product_category', function (Blueprint $table): void {
            $table->dropIndex('ppc_sort_order_index');
            $table->dropColumn('sort_order');
        });
    }
};