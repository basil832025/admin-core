<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_establishment_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('author_name', 255);
            $table->string('author_avatar', 255)->nullable();
            $table->tinyInteger('rating')->default('5');
            $table->text('text');
            $table->text('email');
            $table->boolean('is_active')->default('1');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_establishment_reviews');
    }
};
