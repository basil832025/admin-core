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
        Schema::create('bs_banners', function (Blueprint $table) {
            $table->id();

            // Тексты (переводимые через Spatie Translatable)
            $table->json('title')->nullable();        // Заголовок баннера
            $table->json('subtitle')->nullable();     // Подзаголовок / доп. текст
            $table->json('button_text')->nullable();  // Текст кнопки "Детальніше"

            // Картинки
            $table->string('image', 255);             // Основное изображение (desktop)
            $table->string('image_mobile', 255)->nullable(); // Картинка для мобилки (если нужна)

            // Ссылка при клике
            $table->string('url', 512)->nullable();   // Куда ведёт баннер
            $table->string('target', 20)->default('_self'); // '_self' или '_blank'

            // Позиционирование и статус
            $table->unsignedInteger('sort')->default(0); // Сортировка в слайдере
            $table->boolean('is_active')->default(true); // Включен/выключен

            // Ограничение по датам показа (если понадобится)
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_banners');
    }
};
