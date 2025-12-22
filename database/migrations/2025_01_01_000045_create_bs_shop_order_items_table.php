<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('sort')->default('0');
            $table->unsignedBigInteger('shop_order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('sku', 64)->nullable();
            $table->json('product_snapshot')->nullable();
            $table->json('promotion_data')->nullable();
            $table->integer('qty');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('unit_price_effective', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->default('0.00');
            $table->decimal('discount_total', 12, 2)->default('0.00');
            $table->decimal('tax_rate', 5, 2)->default('0.00');
            $table->decimal('tax_total', 12, 2)->default('0.00');
            $table->decimal('total', 12, 2)->default('0.00');
            $table->json('stage_flags')->nullable();
            $table->string('currency', 3)->default('UAH');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['shop_order_id'], 'shop_order_items_shop_order_id_index');
            $table->index(['product_id'], 'shop_order_items_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_order_items');
    }
};
