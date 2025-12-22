<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_site_text_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 255);
            $table->json('title')->nullable();
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('position')->default('0');
            $table->boolean('active')->default('1');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'bs_site_text_groups_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_site_text_groups');
    }
};
