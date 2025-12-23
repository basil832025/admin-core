<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_characteristic_value', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('bs_products')
                ->cascadeOnDelete();
            $table->foreignId('characteristic_id')
                ->constrained('bs_characteristics')
                ->cascadeOnDelete();
            $table->foreignId('characteristic_value_id')
                ->nullable()
                ->constrained('bs_characteristic_values')
                ->nullOnDelete();
            $table->decimal('price_modifier', 10, 2)->nullable(); // Надбавка (или фикс)
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 10, 2)->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->timestamps();
            // Уникальность: не допускаем дубли
            $table->unique([
                'product_id',
                'characteristic_id',
                'characteristic_value_id'
            ], 'unique_product_characteristic_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_characteristic_value');
    }
};
