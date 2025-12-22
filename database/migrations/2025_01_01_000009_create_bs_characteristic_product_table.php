<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_characteristic_product', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->boolean('affects_price')->default('0');
            $table->decimal('price_modifier', 10, 2)->nullable();
            $table->text('modifier_type')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['product_id'], 'characteristic_product_product_id_foreign');
            $table->index(['characteristic_id'], 'characteristic_product_characteristic_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_characteristic_product');
    }
};
