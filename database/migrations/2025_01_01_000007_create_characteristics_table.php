<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_characteristics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('bs_characteristic_categories')
                ->onDelete('cascade');
            $table->json('name')->comment('Название характеристики');
            $table->string('slug')->unique()->comment('URL-friendly код');
            // Тип ценообразования: 0=Не влияет,1=Надбавка,2=Фиксированная
            $table->tinyInteger('pricing_type')->default(0)->comment(
                '0=Не влияет,1=Надбавка,2=Фиксированная'
            );
            // Позиция сортировки
            $table->integer('sort_order')->default(0);
            // Раскрыть все значения при выборе
            $table->boolean('expand_values')->default(false)
                ->comment('Раскрыть все значения в товаре');
            // Обязательная характеристика
            $table->boolean('is_required')->default(false)
                ->comment('Обязательная для заполнения');
            // Тип поля
            $table->string('field_type')->comment(
                'html input type or select type identifier'
            );
            // Активность
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main_tab')->default(false);
            $table->foreignId('svg_image_id')
                ->nullable()
                ->constrained('bs_svg_images')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_characteristics');
    }
};
