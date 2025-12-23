<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->nullable()->constrained('bs_shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('bs_products')->cascadeOnDelete();
            $table->string('sku',64)->nullable();
            $table->integer('qty');
            $table->string('currency',3)->nullable();
            $table->json('product_snapshot')->nullable();
            $table->json('promotion_data')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('sort')->default(0);
            $table->decimal('unit_price_effective', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('stage_flags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_order_items');
    }
};
