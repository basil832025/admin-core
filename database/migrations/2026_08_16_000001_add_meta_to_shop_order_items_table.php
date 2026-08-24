<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bs_shop_order_items', 'meta')) {
            Schema::table('bs_shop_order_items', function (Blueprint $table): void {
                $table->json('meta')->nullable()->after('promotion_data');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bs_shop_order_items', 'meta')) {
            Schema::table('bs_shop_order_items', function (Blueprint $table): void {
                $table->dropColumn('meta');
            });
        }
    }
};
