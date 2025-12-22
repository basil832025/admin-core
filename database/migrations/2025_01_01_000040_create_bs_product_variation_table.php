<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_variation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id');
            $table->decimal('price', 10, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['product_id', 'variation_id'], 'product_variation_product_id_variation_id_unique');
            $table->index(['variation_id'], 'product_variation_variation_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_variation');
    }
};
