<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_variation_characteristic_value', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');
            $table->foreign('variation_id', 'fk_var_char_val_var_id')
                ->references('id')
                ->on('bs_variations')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('characteristic_id');
            $table->foreign('characteristic_id', 'fk_var_char_val_char_id')
                ->references('id')
                ->on('bs_characteristics')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('characteristic_value_id');
            $table->foreign('characteristic_value_id', 'fk_var_char_val_char_val_id')
                ->references('id')
                ->on('bs_characteristic_values')
                ->cascadeOnDelete();
            $table->unique(['variation_id', 'characteristic_id'], 'uniq_variation_char');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_variation_characteristic_value');
    }
};
