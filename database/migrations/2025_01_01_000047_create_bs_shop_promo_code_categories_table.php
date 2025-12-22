<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_code_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['promo_code_id', 'category_id'], 'spcc_unique');
            $table->index(['promo_code_id'], 'spcc_pc_idx');
            $table->index(['category_id'], 'spcc_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_code_categories');
    }
};
