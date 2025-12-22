<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_calculation_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('calculation_id');
            $table->unsignedBigInteger('component_product_id');
            $table->decimal('qty', 10, 3)->default('1.000');
            $table->decimal('unit_cost', 12, 2)->default('0.00');
            $table->decimal('row_cost', 12, 2)->default('0.00');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['calculation_id', 'component_product_id'], 'uniq_calc_component');
            $table->index(['component_product_id'], 'product_calculation_items_component_product_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_calculation_items');
    }
};
