<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_variation_characteristic_value', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('variation_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->unsignedBigInteger('characteristic_value_id');
            $table->unique(['variation_id', 'characteristic_id'], 'uniq_variation_char');
            $table->index(['characteristic_id'], 'variation_characteristic_value_characteristic_id_foreign');
            $table->index(['characteristic_value_id'], 'variation_characteristic_value_characteristic_value_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_variation_characteristic_value');
    }
};
