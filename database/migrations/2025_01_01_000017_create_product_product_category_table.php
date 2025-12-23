<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_product_category', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('bs_products')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('bs_product_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_product_category');
    }
};
