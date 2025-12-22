<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('bs_locations', function (Blueprint $t) {
            $t->id();
            // мультиязычные поля (JSON): {ua:"...", ru:"...", en:"..."}
            $t->json('title');
            $t->json('city')->nullable();
            $t->json('address')->nullable();

            // координаты точки
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();

            // ссылка на иконку маркера (модель SvgImage)
            $t->foreignId('svg_image_id')->nullable()->constrained('bs_svg_images')->nullOnDelete();

            // контакты в виде builder-массивов
            // phones/emails — массив блоков: [{slug, value, is_active, note}]
            $t->json('phones')->nullable();
            $t->json('emails')->nullable();

            // произвольный график работы
            $t->json('schedule')->nullable();

            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(100);
            $t->string('slug')->unique();
            $t->timestamps();

            $t->index(['is_active', 'sort']);
        });
    }
    public function down(): void { Schema::dropIfExists('bs_locations'); }

};
