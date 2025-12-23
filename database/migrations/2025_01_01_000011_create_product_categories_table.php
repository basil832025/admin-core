<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_categories', function (Blueprint $table) {
            $table->id();
            $table->json('title'); // Для мультиязычности
            $table->string('slug')->unique();
         //   $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->bigInteger('parent_id')->default(-1)->nullable();
            $table->integer('order')->default(0)->index();
             $table->boolean('is_visible')->default(true);
            $table->json('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_categories');
    }
};
