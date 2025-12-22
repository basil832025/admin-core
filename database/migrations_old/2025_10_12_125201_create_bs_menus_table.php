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
            $table->json('title')->nullable();            // мультиязычное название (админкам удобно)
            $table->string('slug', 64)->unique();         // header, footer_left, sidebar_catalog и т.д.
            $table->string('locale', 8)->nullable();      // 'uk','ru','en' — если меню различается по языку
            $table->unsignedTinyInteger('max_depth')->default(1); // 0/1=flat, >=2=tree
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamps();

            $table->index(['slug', 'locale']);
            $table->index(['is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_menus');
    }
};
