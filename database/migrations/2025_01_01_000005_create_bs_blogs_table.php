<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_blogs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('blog_category_id');
            $table->json('title');
            $table->string('slug', 255);
            $table->json('anons')->nullable();
            $table->json('content');
            $table->string('preview_image', 255)->nullable();
            $table->json('preview_image_i18n')->nullable();
            $table->string('detail_image', 255)->nullable();
            $table->json('detail_image_i18n')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default('0');
            $table->timestamp('published_at')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unique(['slug'], 'blogs_slug_unique');
            $table->index(['blog_category_id'], 'blogs_blog_category_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_blogs');
    }
};
