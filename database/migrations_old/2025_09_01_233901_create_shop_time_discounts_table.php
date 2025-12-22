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
        Schema::create('shop_time_discounts', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);

            // Мультиселект дней недели: храним как JSON-массив чисел 1..7 (1=Пн ... 7=Вс)
            $table->json('days')->nullable();

            // Время "с" и "по": формат TIME (локальное время магазина)
            $table->time('time_from')->nullable();
            $table->time('time_to')->nullable();

            // Какое время проверяем: создание заказа / выполнение (доставка/выдача)
            $table->enum('time_type', ['order_created', 'order_fulfilled'])
                ->default('order_created')
                ->index();

            // Правило: каждый N-й товар получает скидку
            $table->unsignedInteger('nth_item')->default(1); // 1 = каждый товар
            $table->decimal('percent', 5, 2);                // 0.01..100.00

            $table->boolean('is_active')->default(true)->index();

            // Период действия акции (опционально)
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();

            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Ограничения на percent и nth_item (MySQL 8+)
        try {
            DB::statement("
                ALTER TABLE shop_time_discounts
                ADD CONSTRAINT chk_shop_time_discounts_percent
                CHECK (percent > 0 AND percent <= 100)
            ");
            DB::statement("
                ALTER TABLE shop_time_discounts
                ADD CONSTRAINT chk_shop_time_discounts_nth
                CHECK (nth_item >= 1)
            ");
        } catch (\Throwable $e) {
            // на некоторых СУБД/сборках CHECK может отсутствовать — пропускаем
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_time_discounts');
    }
};
