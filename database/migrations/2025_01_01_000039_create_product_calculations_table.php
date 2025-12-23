<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bs_product_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('valid_from');            // с какой даты действует
            $table->date('valid_to')->nullable();  // по какую (включительно), null = бессрочно
            $table->string('name')->nullable();    // произвольное имя/версия (опц.)
            $table->text('note')->nullable();      // комментарий
            $table->decimal('total_cost', 12, 2)->default(0); // агрегированная сумма по позициям
            $table->timestamps();
            $table->index(['product_id','valid_from','valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bs_product_calculations');
    }
};
