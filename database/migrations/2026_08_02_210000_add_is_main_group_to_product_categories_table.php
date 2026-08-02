<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_product_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_product_categories', 'is_main_group')) {
                $table->boolean('is_main_group')
                    ->default(false)
                    ->after('is_visible')
                    ->index();
            }
        });

        $mainGroupIds = DB::table('bs_product_categories')
            ->where('is_main_group', true)
            ->orderBy('id')
            ->pluck('id');

        if ($mainGroupIds->count() > 1) {
            DB::table('bs_product_categories')
                ->whereIn('id', $mainGroupIds->slice(1)->all())
                ->update(['is_main_group' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('bs_product_categories', function (Blueprint $table) {
            if (Schema::hasColumn('bs_product_categories', 'is_main_group')) {
                $table->dropColumn('is_main_group');
            }
        });
    }
};