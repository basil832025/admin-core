<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_code_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('product_id');
            $table->index('promo_code_id', 'spcp_pc_idx');
            $table->index('product_id', 'spcp_prod_idx');
            $table->unique(['promo_code_id','product_id'], 'spcp_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_code_products');
    }
};
