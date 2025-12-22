<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_shop_time_discount_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('time_discount_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['time_discount_id', 'category_id'], 'stdc_unique');
            $table->index(['time_discount_id'], 'shop_time_discount_categories_time_discount_id_index');
            $table->index(['category_id'], 'shop_time_discount_categories_category_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_shop_time_discount_categories');
    }
};
