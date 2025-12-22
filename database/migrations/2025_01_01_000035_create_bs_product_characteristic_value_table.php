<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_characteristic_value', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->unsignedBigInteger('characteristic_value_id')->nullable();
            $table->text('value_text');
            $table->decimal('value_number', 10, 2)->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->decimal('price_modifier', 10, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['product_id', 'characteristic_id', 'characteristic_value_id'], 'unique_product_characteristic_value');
            $table->index(['characteristic_id'], 'product_characteristic_value_characteristic_id_foreign');
            $table->index(['characteristic_value_id'], 'product_characteristic_value_characteristic_value_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_characteristic_value');
    }
};
