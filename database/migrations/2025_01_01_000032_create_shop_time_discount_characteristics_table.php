<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discount_characteristics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('time_discount_id');
            $table->unsignedBigInteger('characteristic_id');
            // короткие индексы
            $table->index('time_discount_id', 'stdc_td_idx');
            $table->index('characteristic_id', 'stdc_char_idx');
            $table->timestamps();
            // уникальная пара
            $table->unique(['time_discount_id', 'characteristic_id'], 'stdc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discount_characteristics');
    }
};
