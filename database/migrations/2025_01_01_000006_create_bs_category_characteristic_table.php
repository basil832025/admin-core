<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_category_characteristic', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('characteristic_id');
            $table->boolean('affects_price')->default('0');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('is_required')->default('0');
            $table->index(['category_id'], 'category_characteristic_category_id_foreign');
            $table->index(['characteristic_id'], 'category_characteristic_characteristic_id_foreign');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_category_characteristic');
    }
};
