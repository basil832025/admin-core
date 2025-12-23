<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_pages', function (Blueprint $table) {
            $table->id();
            // уникальный идентификатор страницы в URL
            $table->string('slug')->unique()->comment('URL-имя страницы');
            // JSON-поля: ключ — код языка, значение — текст
            $table->json('title')->comment('Заголовок страницы (мультиязычный)');
            $table->json('content')->comment('Контент страницы (мультиязычный)');
            // SEO-мета тоже в JSON, чтобы для каждого языка можно было задать своё
            $table->json('meta_title')->nullable()->comment('Meta Title (мультиязычный)');
            $table->json('meta_description')->nullable()->comment('Meta Description (мультиязычный)');
            $table->json('meta_keywords')->nullable()->comment('Meta Keywords (мультиязычный)');
            // статус: черновик или опубликована
            $table->enum('status', ['draft', 'published'])
                ->default('draft')
                ->comment('Статус страницы');
            $table->json('fields')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_pages');
    }
};
