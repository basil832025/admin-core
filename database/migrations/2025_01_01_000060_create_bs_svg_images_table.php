<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_svg_images', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 255);
            $table->string('title', 255)->nullable();
            $table->text('description');
            $table->boolean('is_attr')->default('0');
            $table->longText('svg_code');
            $table->string('default_color', 16)->nullable();
            $table->json('color_variants')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_svg_images_slug_unique');
            $table->index(['is_attr'], 'idx_svg_images_is_attr');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_svg_images');
    }
};
