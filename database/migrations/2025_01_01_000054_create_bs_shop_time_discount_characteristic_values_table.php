<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discount_characteristic_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('time_discount_id');
            $table->unsignedBigInteger('characteristic_value_id');
            $table->unsignedBigInteger('characteristic_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['time_discount_id', 'characteristic_value_id'], 'stdcv_unique');
            $table->index(['time_discount_id'], 'stdcv_td_idx');
            $table->index(['characteristic_value_id'], 'stdcv_val_idx');
            $table->index(['characteristic_id'], 'stdcv_char_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discount_characteristic_values');
    }
};
