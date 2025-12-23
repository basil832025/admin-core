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
        Schema::create('bs_shop_order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')
                ->constrained('bs_shop_orders')
                ->cascadeOnDelete();
            $table->foreignId('shop_order_item_id')
                ->nullable()
                ->constrained('bs_shop_order_items')
                ->nullOnDelete(); // null => корректировка на весь заказ

            $table->string('type', 32);  // promotion|coupon|manual|shipping|tax
            $table->string('label');     // «2-й товар −50%», «Промокод ABC»
            $table->decimal('amount', 12, 2); // скидка < 0, надбавка > 0
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->unsignedBigInteger('promo_code_id')->nullable();
            $table->json('meta')->nullable(); // любые детали правила
            $table->timestamps();

            $table->index(['shop_order_id']);
            $table->index(['shop_order_item_id']);
            $table->index(['type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bs_shop_order_adjustments');
    }
};
