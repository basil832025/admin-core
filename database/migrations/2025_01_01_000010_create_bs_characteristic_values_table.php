<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_characteristic_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('characteristic_id');
            $table->json('value');
            $table->integer('sort_order')->default('0');
            $table->boolean('is_active')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['characteristic_id'], 'characteristic_values_characteristic_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_characteristic_values');
    }
};
