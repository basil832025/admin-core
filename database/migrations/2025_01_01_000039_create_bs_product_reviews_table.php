<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->string('name', 120);
            $table->string('email', 190);
            $table->tinyInteger('rating')->unsigned();
            $table->text('content');
            $table->string('status', 20)->default('pending');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->index(['product_id', 'created_at'], 'bs_product_reviews_product_id_created_at_index');
            $table->index(['status'], 'bs_product_reviews_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_reviews');
    }
};
