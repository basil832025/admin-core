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
        Schema::create('shop_promo_code_characteristics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promo_code_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->index('promo_code_id', 'spch_pc_idx');
            $table->index('characteristic_id', 'spch_char_idx');
            $table->unique(['promo_code_id','characteristic_id'], 'spch_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_promo_code_characteristics');
    }
};
