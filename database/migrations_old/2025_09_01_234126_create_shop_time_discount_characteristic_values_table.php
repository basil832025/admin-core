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
        Schema::create('shop_time_discount_characteristic_values', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('time_discount_id');
            $table->unsignedBigInteger('characteristic_value_id');
            $table->unsignedBigInteger('characteristic_id')->nullable();

            // короткие имена индексов (важно!)
            $table->index('time_discount_id', 'stdcv_td_idx');
            $table->index('characteristic_value_id', 'stdcv_val_idx');
            $table->index('characteristic_id', 'stdcv_char_idx');

            $table->timestamps();

            // и короткое имя уникального индекса
            $table->unique(
                ['time_discount_id', 'characteristic_value_id'],
                'stdcv_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_time_discount_characteristic_values');
    }
};
