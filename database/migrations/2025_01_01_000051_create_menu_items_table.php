<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_menu_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('menu_id');            // FK -> bs_menus
            $table->bigInteger('parent_id')->default(-1);     // root = -1 (signed!)
            $table->json('label');                             // {"uk":"Про нас","en":"About"}
            $table->string('link_type', 32)->default('url');   // page|category|blog|blog_category|url
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url', 512)->nullable();
            $table->string('icon', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(100);
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_to')->nullable();
            $table->boolean('auth_only')
                ->default(false)
                ->comment('Показывать только авторизованным пользователям');
            $table->timestamps();
            // --- Внешние ключи ---
            $table->foreign('menu_id')
                ->references('id')->on('bs_menus')
                ->onDelete('cascade');
            // ВАЖНО: на parent_id FK НЕ СТАВИМ (из-за -1)!
            // --- Индексы ---
            $table->index(['menu_id', 'parent_id'], 'bs_menu_items_menu_parent_idx');
            $table->index(['menu_id', 'sort'], 'bs_menu_items_menu_sort_idx');
            $table->index(['is_active', 'visible_from', 'visible_to'], 'bs_menu_items_visible_idx');
            $table->index(['link_type', 'target_id'], 'bs_menu_items_link_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_menu_items');
    }
};
