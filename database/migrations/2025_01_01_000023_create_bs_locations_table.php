<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_locations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('title');
            $table->json('city')->nullable();
            $table->json('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedBigInteger('svg_image_id')->nullable();
            $table->json('phones')->nullable();
            $table->json('emails')->nullable();
            $table->json('schedule')->nullable();
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('sort')->default('100');
            $table->string('slug', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_locations_slug_unique');
            $table->index(['svg_image_id'], 'bs_locations_svg_image_id_foreign');
            $table->index(['is_active', 'sort'], 'bs_locations_is_active_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_locations');
    }
};
