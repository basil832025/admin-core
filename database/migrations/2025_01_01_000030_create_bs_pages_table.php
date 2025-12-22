<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('slug', 255);
            $table->json('title');
            $table->json('content');
            $table->json('fields')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->text('status')->default('draft');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['slug'], 'pages_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_pages');
    }
};
