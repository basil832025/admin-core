<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_calculation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_id')->constrained('product_calculations')->cascadeOnDelete();
            // компонент-товар из того же каталога products, НО не сам продукт-калькулируемый
            $table->foreignId('component_product_id')->constrained('products');
            $table->decimal('qty', 10, 3)->default(1);       // количество компонента
            $table->decimal('unit_cost', 12, 2)->default(0); // себестоимость за 1 ед. (снимок)
            $table->decimal('row_cost', 12, 2)->default(0);  // сумма = qty * unit_cost (снимок)
            $table->timestamps();
            $table->unique(['calculation_id', 'component_product_id'], 'uniq_calc_component'); // без дублей
            $table->index(['component_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_calculation_items');
    }
};
