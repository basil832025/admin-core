<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_blog_comments', function (Blueprint $table) {
            $table->id();
            // Привязка к записи блога
            $table->foreignId('blog_id')
                ->constrained('bs_blogs')
                ->onDelete('cascade');
            // Для зарегистрированных пользователей (если есть таблица users)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            // Для гостей (если нужно)
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            // Текст комментария
            $table->text('content');
            // Вложенность комментариев
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('bs_blog_comments')
                ->onDelete('cascade');
            // Модерация
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_blog_comments');
    }
};
