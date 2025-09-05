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
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('sku',64)->nullable();

            $table->integer('qty');
            $table->string('currency',3);

            $table->json('product_snapshot')->nullable();
            $table->json('promotion_data')->nullable();

            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
    }
};
