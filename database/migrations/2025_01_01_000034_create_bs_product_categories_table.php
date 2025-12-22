<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('title');
            $table->string('slug', 255);
            $table->bigInteger('parent_id')->default('-1');
            $table->integer('order')->default('0');
            $table->boolean('is_visible')->default('1');
            $table->json('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'product_categories_slug_unique');
            $table->index(['order'], 'product_categories_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_categories');
    }
};
