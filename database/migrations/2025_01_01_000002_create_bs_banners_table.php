<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_banners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->json('title')->nullable();
            $table->json('subtitle')->nullable();
            $table->json('button_text')->nullable();
            $table->string('image', 255);
            $table->json('images')->nullable();
            $table->string('image_mobile', 255)->nullable();
            $table->string('url', 512)->nullable();
            $table->string('target', 20)->default('_self');
            $table->unsignedInteger('sort')->default('0');
            $table->boolean('is_active')->default('1');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_banners');
    }
};
