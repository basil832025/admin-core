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
        Schema::create('shop_time_discount_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_discount_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->timestamps();

            $table->unique(['time_discount_id', 'product_id'], 'stdp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_time_discount_products');
    }
};
