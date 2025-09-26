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
            $table->boolean('is_new')->default(false)->after('id');
            $table->boolean('is_hit')->default(false)->after('is_new');
            $table->boolean('is_home')->default(false)->after('is_hit');

            $table->string('code2')->nullable()->after('is_home')
                ->comment('Внешний код для связи с программой на ПК');

            $table->integer('sort')->default(0)->after('code2')
                ->comment('Сортировка товара в категории');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_new', 'is_hit', 'is_home', 'code2', 'sort']);
        });
    }
};
