<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discounts', function (Blueprint $table) {
            $table->id();
            $table->json('name')->nullable(); // Мультиязычное название (было string(160))
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
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discounts');
    }
};
