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
        Schema::create('characteristic_values', function (Blueprint $table) {
            $table->id();
            // связь с характеристикой
            $table->foreignId('characteristic_id')
                ->constrained('characteristics')
                ->cascadeOnDelete();

            $table->json('value')->comment('Значение характеристики');
            $table->integer('sort_order')->default(0)->comment('Позиция сортировки');
            $table->boolean('is_active')->default(true)->comment('Активно');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characteristic_values');
    }
};
