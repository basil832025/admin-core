<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_code_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->timestamp('used_at')->useCurrent();
            $table->unique(['promo_code_id', 'order_id'], 'spcu_pc_order_unique');
            $table->index(['promo_code_id'], 'spcu_pc_idx');
            $table->index(['client_id'], 'spcu_client_idx');
            $table->index(['order_id'], 'spcu_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_code_usages');
    }
};
