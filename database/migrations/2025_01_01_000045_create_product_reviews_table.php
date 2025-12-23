<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_reviews', function (Blueprint $table) {
            $table->id();
            // связь с продуктом
            $table->foreignId('product_id')
                ->constrained('bs_products')
                ->cascadeOnDelete();
            // поля формы
            $table->string('name', 120);
            $table->string('email', 190);
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->text('content')->nullable();   // «Напишите отзыв…»
            // служебные
            $table->string('status', 20)->default('pending')->index(); // ReviewStatus::Pending
            $table->ipAddress('ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps(); // created_at = дата/время добавления
            $table->softDeletes();
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_reviews');
    }
};
