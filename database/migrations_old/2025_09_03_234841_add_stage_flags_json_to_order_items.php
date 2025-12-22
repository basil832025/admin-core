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
        Schema::table('shop_order_items', function (Blueprint $table) {
            // JSON под флаги этапов. Делаем nullable, чтобы не ломать старые вставки.
            $table->json('stage_flags')->nullable()->after('total');
        });

        // Базовая инициализация для уже существующих строк
        // accepted — «Принял», filling — «Начинка», molding — «Лепка», baking — «Печь», ready — «Приготовлен»
        DB::table('shop_order_items')
            ->whereNull('stage_flags')
            ->update([
                'stage_flags' => json_encode([
                    'accepted' => false,
                    'filling'  => false,
                    'molding'  => false,
                    'baking'   => false,
                    'ready'    => false,
                ], JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn('stage_flags');
        });
    }
};
