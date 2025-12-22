<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            // Момент фактической оплаты заказа
            $table->timestamp('paid_at')
                ->nullable()
                ->after('date_order');

            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropColumn('paid_at');
        });
    }
};
