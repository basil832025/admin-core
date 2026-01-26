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
        Schema::create('bs_delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название зоны (например, "Green_1", "Зеленая зона 1")
            $table->text('description')->nullable(); // Описание зоны
            $table->string('color', 7)->default('#00FF00'); // Цвет зоны в HEX формате
            $table->decimal('delivery_price', 10, 2)->default(0); // Цена доставки
            $table->integer('delivery_time_min')->default(30); // Минимальное время доставки (минуты)
            $table->integer('delivery_time_max')->default(60); // Максимальное время доставки (минуты)
            $table->decimal('free_delivery_from', 10, 2)->default(0); // Сумма от которой бесплатная доставка
            $table->integer('sort_order')->default(0); // Порядок сортировки
            $table->boolean('is_active')->default(true); // Активна ли зона
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bs_delivery_zones');
    }
};
