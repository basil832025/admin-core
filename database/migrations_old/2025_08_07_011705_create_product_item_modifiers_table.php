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
        Schema::create('product_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')
                ->references('id')
                ->on('shop_order_items')
                ->onDelete('cascade');
            $table->enum('type', ['variation', 'characteristic']);
            $table->unsignedBigInteger('value_id');
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_item_modifiers');
    }
};
