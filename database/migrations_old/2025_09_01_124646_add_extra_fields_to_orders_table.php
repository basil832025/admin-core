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
        Schema::table('shop_orders', function (Blueprint $table) {
            // Дата создания заказа (только дата)
            $table->date('dat')->nullable()->comment('Дата создания заказа (только дата)');

            // Время создания заказа
            $table->time('time_start')->nullable()->comment('Время создания заказа');

            // Время заказа (доставка/самовывоз)
            $table->time('time_order')->nullable()->comment('Время выполнения заказа');

            // Дата заказа (по умолчанию = dat, меняется при отложенных заказах)
            $table->date('date_order')->nullable()->comment('Дата выполнения заказа');

            // Флажок самовывоз
            $table->boolean('self_pickup')->default(false)->comment('Самовывоз');

            // Флажок "как можно скорее"
            $table->boolean('as_soon_possible')->default(false)->comment('Как можно скорее');

            // Способ оплаты (1 - Наличка, 2 - Безнал, 3 - Клубная карта, 4 - Кредитная карта, 5 - Без оплаты)
            $table->tinyInteger('payment')->default(1)->comment('Способ оплаты');

            // Причина неоплаты
            $table->string('reason_non_payment')->nullable()->comment('Причина неоплаты');

            // Скидка %
            $table->decimal('sale_prc', 5, 2)->default(0)->comment('Процентная скидка');

            // Скидка суммой
            $table->decimal('sale_sum', 12, 2)->default(0)->comment('Суммовая скидка');

            // Итоговая сумма со скидкой
            $table->decimal('total_price_sale', 12, 2)->default(0)->comment('Итоговая сумма со скидкой');


        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn([
                'dat',
                'time_start',
                'time_order',
                'date_order',
                'self_pickup',
                'as_soon_possible',
                'payment',
                'reason_non_payment',
                'sale_prc',
                'sale_sum',
                'total_price_sale',
                'short_name',
            ]);
        });
    }
};
