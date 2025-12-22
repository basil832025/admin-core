<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_promo_code_characteristics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['promo_code_id', 'characteristic_id'], 'spch_unique');
            $table->index(['promo_code_id'], 'spch_pc_idx');
            $table->index(['characteristic_id'], 'spch_char_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_promo_code_characteristics');
    }
};
