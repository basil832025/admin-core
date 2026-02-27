<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'cash_from')) {
                $table->decimal('cash_from', 12, 2)->nullable()->after('payment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'cash_from')) {
                $table->dropColumn('cash_from');
            }
        });
    }
};
