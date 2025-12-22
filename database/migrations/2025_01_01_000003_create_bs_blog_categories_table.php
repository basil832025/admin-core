<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_blog_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('name');
            $table->string('slug', 255);
            $table->json('description')->nullable();
            $table->integer('sort_order')->default('0');
            $table->boolean('is_active')->default('1');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->string('image', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'blog_categories_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_blog_categories');
    }
};
