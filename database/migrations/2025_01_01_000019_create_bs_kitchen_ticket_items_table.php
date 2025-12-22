<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_kitchen_ticket_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kitchen_ticket_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('product_name', 255)->nullable();
            $table->string('product_short', 255)->nullable();
            $table->decimal('qty', 10, 2)->default('1.00');
            $table->string('stage', 255)->default('filling');
            $table->timestamp('filling_at')->nullable();
            $table->timestamp('molding_at')->nullable();
            $table->timestamp('baking_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['kitchen_ticket_id', 'order_item_id'], 'kitchen_ticket_items_kitchen_ticket_id_order_item_id_unique');
            $table->index(['kitchen_ticket_id', 'stage'], 'kitchen_ticket_items_kitchen_ticket_id_stage_index');
            $table->index(['order_item_id'], 'kitchen_ticket_items_order_item_id_index');
            $table->index(['stage'], 'kitchen_ticket_items_stage_index');
            $table->index(['filling_at'], 'kitchen_ticket_items_filling_at_index');
            $table->index(['molding_at'], 'kitchen_ticket_items_molding_at_index');
            $table->index(['baking_at'], 'kitchen_ticket_items_baking_at_index');
            $table->index(['prepared_at'], 'kitchen_ticket_items_prepared_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_kitchen_ticket_items');
    }
};
