<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_new')->default('0');
            $table->boolean('is_hit')->default('0');
            $table->boolean('is_home')->default('0');
            $table->string('code2', 255)->nullable();
            $table->integer('sort')->default('0');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('sku', 255)->nullable();
            $table->json('title');
            $table->text('short_desc');
            $table->string('short_name', 255)->nullable();
            $table->string('slug', 255);
            $table->string('main_image', 255)->nullable();
            $table->string('main_image_small', 255)->nullable();
            $table->json('description')->nullable();
            $table->text('dop_info');
            $table->decimal('price', 10, 2)->default('0.00');
            $table->decimal('old_price', 10, 2)->nullable();
            $table->boolean('in_stock')->default('1');
            $table->integer('quantity')->default('0');
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'products_slug_unique');
            $table->unique(['sku'], 'products_sku_unique');
            $table->index(['parent_id'], 'products_parent_id_foreign');
            $table->index(['category_id'], 'products_category_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_products');
    }
};
