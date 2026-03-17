<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_shop_orders', 'kitchen_print_count')) {
                $table->unsignedInteger('kitchen_print_count')->default(0)->after('synced_at');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'kitchen_last_printed_at')) {
                $table->dateTime('kitchen_last_printed_at')->nullable()->after('kitchen_print_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            if (Schema::hasColumn('bs_shop_orders', 'kitchen_last_printed_at')) {
                $table->dropColumn('kitchen_last_printed_at');
            }

            if (Schema::hasColumn('bs_shop_orders', 'kitchen_print_count')) {
                $table->dropColumn('kitchen_print_count');
            }
        });
    }
};
