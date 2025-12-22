<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discount_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('time_discount_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['time_discount_id', 'product_id'], 'stdp_unique');
            $table->index(['time_discount_id'], 'shop_time_discount_products_time_discount_id_index');
            $table->index(['product_id'], 'shop_time_discount_products_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discount_products');
    }
};
