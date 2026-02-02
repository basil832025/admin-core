<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            // Где действует акция: delivery/pickup. null/[] = по умолчанию "везде"
            $table->json('channels')
                ->nullable()
                ->after('time_type');

            // Как формировать группы N-товара
            // price_sorted  = сортировать по цене и группировать (как сейчас)
            // cart_order    = по порядку добавления в чек
            $table->string('grouping_mode', 32)
                ->default('price_sorted')
                ->after('channels');

            // На какой товар в группе применять скидку
            // cheapest, most_expensive, index
            $table->string('apply_target', 32)
                ->default('cheapest')
                ->after('grouping_mode');

            // Если apply_target = index, то индекс (1..N)
            $table->unsignedTinyInteger('apply_index')
                ->nullable()
                ->after('apply_target');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->dropColumn(['channels', 'grouping_mode', 'apply_target', 'apply_index']);
        });
    }
};
