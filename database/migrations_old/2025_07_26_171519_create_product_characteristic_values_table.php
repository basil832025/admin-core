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
        Schema::create('product_characteristic_value', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('characteristic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('characteristic_value_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->decimal('price_modifier', 10, 2)->nullable(); // Надбавка (или фикс)

            $table->timestamps();

            // Уникальность: не допускаем дубли
            $table->unique([
                'product_id',
                'characteristic_id',
                'characteristic_value_id'
            ], 'unique_product_characteristic_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_characteristic_values');
    }
};
