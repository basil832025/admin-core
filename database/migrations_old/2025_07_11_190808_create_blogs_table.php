<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();

            // связь с категориями блога
            $table->foreignId('blog_category_id')
                ->constrained('blog_categories')
                ->onDelete('cascade');

            // основные поля
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('anons')->nullable();
            $table->json('content');

            // изображения
            $table->string('preview_image')->nullable()->comment('URL или путь к превью');
            $table->string('detail_image')->nullable()->comment('URL или путь к деталке');

            // теги (массив строк)
            $table->json('tags')->nullable()->comment('Список тегов, например ["laravel","php"]');

            // публикация
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // SEO-поля
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
