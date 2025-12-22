<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('site_name', 255)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('favicon_path', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->json('social_links')->nullable();
            $table->string('default_language_code', 5)->nullable();
            $table->string('admin_color_scheme', 20)->default('primary');
            $table->json('admin_settings')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_settings');
    }
};
