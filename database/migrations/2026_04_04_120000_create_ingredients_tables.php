<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_ingredients', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('legacy_consist_id')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('bs_product_ingredient', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('bs_products')
                ->cascadeOnDelete();
            $table->foreignId('ingredient_id')
                ->constrained('bs_ingredients')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id'], 'uniq_product_ingredient');
            $table->index(['product_id', 'sort_order'], 'idx_product_ingredient_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_ingredient');
        Schema::dropIfExists('bs_ingredients');
    }
};
