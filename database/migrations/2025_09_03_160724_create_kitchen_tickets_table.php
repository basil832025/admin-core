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
        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();

            // Привязка к заказу (уже есть shop_orders)
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();

            // Текущий статус кухни (используем OrderStatus)
            $table->string('stage')
                ->default(\App\Enums\OrderStatus::Processing->value)
            ->index();

        // Признаки для фильтров
        $table->boolean('urgent')->default(false)->index();      // «Поскорее»
        $table->string('delivery_type')->nullable()->index();    // delivery / pickup

        // Таймстемпы прохождения ключевых стадий кухни
        $table->timestamp('processing_at')->nullable()->index();
        $table->timestamp('filling_at')->nullable()->index();
        $table->timestamp('molding_at')->nullable()->index();
        $table->timestamp('baking_at')->nullable()->index();
        $table->timestamp('prepared_at')->nullable()->index();

        // Служебные
        $table->timestamps();

        // Быстрые выборки
        $table->index(['stage', 'created_at']);
        $table->unique('order_id');
    });
    }


    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
    }
};
