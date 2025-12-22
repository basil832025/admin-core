<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_order_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shop_order_id');
            $table->unsignedBigInteger('shop_order_item_id')->nullable();
            $table->string('type', 32);
            $table->string('label', 255);
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['shop_order_id'], 'shop_order_adjustments_shop_order_id_index');
            $table->index(['shop_order_item_id'], 'shop_order_adjustments_shop_order_item_id_index');
            $table->index(['type'], 'shop_order_adjustments_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_order_adjustments');
    }
};
