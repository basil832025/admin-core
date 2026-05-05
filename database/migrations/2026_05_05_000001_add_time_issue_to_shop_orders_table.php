<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->time('time_issue')
                ->nullable()
                ->after('time_order')
                ->comment('Время выдачи заказа');
        });

        // Backfill for existing orders: default issue time = order time.
        DB::table('bs_shop_orders')
            ->whereNull('time_issue')
            ->whereNotNull('time_order')
            ->update(['time_issue' => DB::raw('time_order')]);
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->dropColumn('time_issue');
        });
    }
};
