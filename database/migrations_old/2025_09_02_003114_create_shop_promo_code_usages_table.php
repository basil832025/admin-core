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
        Schema::create('shop_promo_code_usages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('client_id')->nullable();   // к какой таблице клиентов мапите — решим в коде
            $table->unsignedBigInteger('order_id')->nullable();

            $table->timestamp('used_at')->useCurrent();

            // индексы (имена короткие)
            $table->index('promo_code_id', 'spcu_pc_idx');
            $table->index('client_id', 'spcu_client_idx');
            $table->index('order_id', 'spcu_order_idx');

            // один и тот же промокод не должен записаться дважды на один и тот же заказ
            $table->unique(['promo_code_id','order_id'], 'spcu_pc_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_promo_code_usages');
    }
};
