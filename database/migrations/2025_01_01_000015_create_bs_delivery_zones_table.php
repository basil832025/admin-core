<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_delivery_zones', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('sort')->default('500');
            $table->string('slug', 255);
            $table->json('title')->nullable();
            $table->unsignedInteger('price_uah');
            $table->unsignedInteger('free_from')->nullable();
            $table->string('color', 16)->default('#3b82f6');
            $table->json('polygons');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_delivery_zones_slug_unique');
            $table->index(['is_active'], 'bs_delivery_zones_is_active_index');
            $table->index(['sort'], 'bs_delivery_zones_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_delivery_zones');
    }
};
