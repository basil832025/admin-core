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
        Schema::create('bs_loyalty_rules', function (Blueprint $table) {
            $table->id();

            // С какой даты правило вступает в силу (включительно)
            $table->date('valid_from')->index();

            // % начисления от суммы заказа
            $table->unsignedTinyInteger('earn_percent')->default(10);

            // Срок жизни обычных бонусов (в днях)
            $table->unsignedSmallInteger('earn_expire_days')->default(30);

            // Приветственный бонус (сумма)
            $table->unsignedInteger('welcome_bonus_amount')->default(50);

            // Срок жизни приветственного бонуса (в днях)
            $table->unsignedSmallInteger('welcome_bonus_expire_days')->default(7);

            // Минимальная сумма заказа для начисления (может быть null — тогда не ограничиваем)
            $table->decimal('min_order_sum_for_earn', 10, 2)->nullable();

            // Включена ли программа лояльности в рамках этого правила
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            // Не даём создать два правила с одинаковой датой старта
            $table->unique('valid_from');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bs_loyalty_rules');
    }
};
