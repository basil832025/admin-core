<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_locations', function (Blueprint $table) {
            $table->id();
            // мультиязычные поля (JSON): {ua:"...", ru:"...", en:"..."}
            $table->json('title');
            $table->json('city')->nullable();
            $table->json('address')->nullable();
            // координаты точки
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // ссылка на иконку маркера (модель SvgImage)
            $table->foreignId('svg_image_id')->nullable()->constrained('bs_svg_images')->nullOnDelete();
            // контакты в виде builder-массивов
            // phones/emails — массив блоков: [{slug, value, is_active, note}]
            $table->json('phones')->nullable();
            $table->json('emails')->nullable();
            // произвольный график работы
            $table->json('schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->string('slug')->unique();
            $table->timestamps();
            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_locations');
    }
};
