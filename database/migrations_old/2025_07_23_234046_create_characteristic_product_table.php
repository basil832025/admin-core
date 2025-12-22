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
        Schema::create('characteristic_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('characteristic_id')->constrained()->cascadeOnDelete();

            $table->boolean('affects_price')->default(false); // влияет ли на цену
            $table->decimal('price_modifier', 10, 2)->nullable(); // например +10
            $table->enum('modifier_type', ['amount', 'percent'])->nullable(); // amount или percent

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characteristic_product');
    }
};
