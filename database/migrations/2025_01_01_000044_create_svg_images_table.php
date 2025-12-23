<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_svg_images', function (Blueprint $table) {
            $table->id();
            // "название на латыни" — используем как slug (уникально, для имени файла)
            $table->string('slug')->unique();
            $table->string('title')->nullable();              // человекочитаемое имя (не обязательно)
            $table->text('description')->nullable();          // описание
            $table->longText('svg_code');                     // исходник SVG
            $table->string('file_path')->nullable();          // public/image/svg/{slug}.svg
            $table->string('default_color', 16)->nullable();
            $table->json('color_variants')->nullable();
            $table->boolean('is_attr')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_svg_images');
    }
};
