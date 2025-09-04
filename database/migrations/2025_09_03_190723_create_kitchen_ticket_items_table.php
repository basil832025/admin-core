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
        Schema::create('kitchen_ticket_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kitchen_ticket_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('order_item_id')->nullable()->index();

            // снапшоты, чтобы не бегать каждый раз в товары
            $t->string('product_name')->nullable();
            $t->string('product_short')->nullable();
            $t->decimal('qty', 10, 2)->default(1);

            // стадия для позиции (используем твой enum OrderStatus)
            $t->string('stage')->default(\App\Enums\OrderStatus::Filling->value)->index();

            // отметки времени этапов (для отчётности)
            $t->timestamp('filling_at')->nullable()->index();
            $t->timestamp('molding_at')->nullable()->index();
            $t->timestamp('baking_at')->nullable()->index();
            $t->timestamp('prepared_at')->nullable()->index();

            $t->timestamps();

            $t->unique(['kitchen_ticket_id', 'order_item_id']);
            $t->index(['kitchen_ticket_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_items');
    }
};
