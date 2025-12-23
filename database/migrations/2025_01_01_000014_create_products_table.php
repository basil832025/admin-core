<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->nullable();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('in_stock')->default(true);
            $table->integer('quantity')->default(0);
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('dop_info')->nullable();
            $table->string('main_image')->nullable();
            $table->string('short_name', 255)->nullable();
            $table->boolean('is_new')->default(false);
            $table->boolean('is_hit')->default(false);
            $table->boolean('is_home')->default(false);
            $table->string('code2')
                ->nullable()
                ->comment('Внешний код для связи с программой на ПК');
            $table->integer('sort')
                ->default(0)
                ->comment('Сортировка товара в категории');
            $table->text('short_desc')->nullable();
            $table->string('main_image_small', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_products');
    }
};
