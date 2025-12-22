<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_item_modifiers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_item_id');
            $table->text('type');
            $table->unsignedBigInteger('value_id');
            $table->decimal('price_modifier', 10, 2)->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['order_item_id'], 'product_item_modifiers_order_item_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_item_modifiers');
    }
};
