<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_calculations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->string('name', 255)->nullable();
            $table->text('note');
            $table->decimal('total_cost', 12, 2)->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['product_id', 'valid_from', 'valid_to'], 'product_calculations_product_id_valid_from_valid_to_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_calculations');
    }
};
