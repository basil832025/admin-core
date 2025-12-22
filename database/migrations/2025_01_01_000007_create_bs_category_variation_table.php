<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_category_variation', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('variation_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['category_id'], 'category_variation_category_id_foreign');
            $table->index(['variation_id'], 'category_variation_variation_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_category_variation');
    }
};
