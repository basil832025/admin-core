<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_characteristics', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_id');
            $table->json('name');
            $table->string('slug', 255);
            $table->unsignedBigInteger('svg_image_id')->nullable();
            $table->tinyInteger('pricing_type')->default('0');
            $table->integer('sort_order')->default('0');
            $table->boolean('expand_values')->default('0');
            $table->boolean('is_required')->default('0');
            $table->boolean('is_main_tab')->default('0');
            $table->string('field_type', 255);
            $table->boolean('is_active')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'characteristics_slug_unique');
            $table->index(['category_id'], 'characteristics_category_id_foreign');
            $table->index(['svg_image_id'], 'idx_characteristics_svg_image');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_characteristics');
    }
};
