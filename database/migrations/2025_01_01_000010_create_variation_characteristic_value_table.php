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
            $table->foreignId('variation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('characteristic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('characteristic_value_id')->constrained()->cascadeOnDelete();
            $table->unique(['variation_id', 'characteristic_id'], 'uniq_variation_char');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_variation_characteristic_value');
    }
};
