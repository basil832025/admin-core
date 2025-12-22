<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // главная категория
            $table->foreignId('category_id')
                ->nullable()
                ->after('parent_id')                              // поставь “после” куда тебе удобно
                ->constrained('product_categories')          // <-- имя твоей таблицы категорий
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // доп. инфо по товару
            $table->text('dop_info')->nullable()->after('description'); // или после нужного поля
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'dop_info']);
        });
    }
};
