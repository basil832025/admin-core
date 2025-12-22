<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_favorites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('product_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['client_id', 'product_id'], 'bs_favorites_client_id_product_id_unique');
            $table->index(['product_id'], 'bs_favorites_product_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_favorites');
    }
};
