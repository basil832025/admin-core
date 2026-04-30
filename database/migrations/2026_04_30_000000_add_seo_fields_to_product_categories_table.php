<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_product_categories', function (Blueprint $table) {
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('description_title')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bs_product_categories', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_keywords',
                'description_title',
            ]);
        });
    }
};
