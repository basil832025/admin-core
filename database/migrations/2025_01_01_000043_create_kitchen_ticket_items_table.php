<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_kitchen_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained('bs_kitchen_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable()->index();
            // снапшоты, чтобы не бегать каждый раз в товары
            $table->string('product_name')->nullable();
            $table->string('product_short')->nullable();
            $table->decimal('qty', 10, 2)->default(1);
            // стадия для позиции (используем твой enum OrderStatus)
            $table->string('stage')->default('filling')->index(); // OrderStatus::Filling
            // отметки времени этапов (для отчётности)
            $table->timestamp('filling_at')->nullable()->index();
            $table->timestamp('molding_at')->nullable()->index();
            $table->timestamp('baking_at')->nullable()->index();
            $table->timestamp('prepared_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['kitchen_ticket_id', 'order_item_id']);
            $table->index(['kitchen_ticket_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_kitchen_ticket_items');
    }
};
