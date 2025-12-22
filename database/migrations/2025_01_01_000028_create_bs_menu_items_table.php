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
            $table->unsignedBigInteger('menu_id');
            $table->bigInteger('parent_id')->default('-1');
            $table->json('title');
            $table->string('link_type', 32)->default('url');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url', 512)->nullable();
            $table->string('icon', 128)->nullable();
            $table->boolean('is_active')->default('1');
            $table->boolean('auth_only')->default('0');
            $table->unsignedInteger('sort')->default('100');
            $table->timestamp('visible_from')->nullable();
            $table->timestamp('visible_to')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['parent_id'], 'bs_menu_items_parent_id_foreign');
            $table->index(['menu_id', 'parent_id'], 'bs_menu_items_menu_id_parent_id_index');
            $table->index(['menu_id', 'sort'], 'bs_menu_items_menu_id_sort_index');
            $table->index(['is_active', 'visible_from', 'visible_to'], 'bs_menu_items_is_active_visible_from_visible_to_index');
            $table->index(['link_type', 'target_id'], 'bs_menu_items_link_type_target_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_menu_items');
    }
};
