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
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();

            // Название — JSON-массив вида { "ua": "Новини", "en": "News", "ru": "Новости" }
            $table->json('name')->comment('Название категории, переводы');

            // URL-slug (не переводимый)
            $table->string('slug')->unique()->comment('ЧПУ-идентификатор (slug)');

            // Описание — тоже JSON-поле с переводами
            $table->json('description')->nullable()->comment('Краткое описание, переводы');

            // Порядок сортировки
            $table->integer('sort_order')->default(0)->comment('Сортировка');

            // Активность категории
            $table->boolean('is_active')->default(true)->comment('Показывать в списке?');

            // SEO-поля — JSON-шаблоны или по языкам
            $table->json('meta_title')->nullable()->comment('SEO Title переводы');
            $table->json('meta_description')->nullable()->comment('SEO Description переводы');
            $table->json('meta_keywords')->nullable()->comment('SEO Keywords переводы');

            // Опционально — картинка/иконка категории
            $table->string('image')->nullable()->comment('Путь к картинке');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
