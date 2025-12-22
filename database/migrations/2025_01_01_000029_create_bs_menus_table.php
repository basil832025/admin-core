<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_menus', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('title')->nullable();
            $table->string('slug', 64);
            $table->string('locale', 8)->nullable();
            $table->tinyInteger('max_depth')->unsigned()->default('1');
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('sort')->default('100');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_menus_slug_unique');
            $table->index(['slug', 'locale'], 'bs_menus_slug_locale_index');
            $table->index(['is_active', 'sort'], 'bs_menus_is_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_menus');
    }
};
