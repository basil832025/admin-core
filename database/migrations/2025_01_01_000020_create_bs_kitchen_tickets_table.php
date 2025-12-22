<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_kitchen_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('stage', 255)->default('processing');
            $table->boolean('urgent')->default('0');
            $table->string('delivery_type', 255)->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('filling_at')->nullable();
            $table->timestamp('molding_at')->nullable();
            $table->timestamp('baking_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['order_id'], 'kitchen_tickets_order_id_unique');
            $table->index(['stage', 'created_at'], 'kitchen_tickets_stage_created_at_index');
            $table->index(['stage'], 'kitchen_tickets_stage_index');
            $table->index(['urgent'], 'kitchen_tickets_urgent_index');
            $table->index(['delivery_type'], 'kitchen_tickets_delivery_type_index');
            $table->index(['processing_at'], 'kitchen_tickets_processing_at_index');
            $table->index(['filling_at'], 'kitchen_tickets_filling_at_index');
            $table->index(['molding_at'], 'kitchen_tickets_molding_at_index');
            $table->index(['baking_at'], 'kitchen_tickets_baking_at_index');
            $table->index(['prepared_at'], 'kitchen_tickets_prepared_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_kitchen_tickets');
    }
};
