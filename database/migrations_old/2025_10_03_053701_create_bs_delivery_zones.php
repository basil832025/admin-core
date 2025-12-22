<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('bs_delivery_zones', function (Blueprint $t) {
            $t->id();
            $t->boolean('is_active')->default(true)->index();
            $t->unsignedInteger('sort')->default(500)->index();

            $t->string('slug')->unique();           // Green_1, Blue_18 и т.п. (как в старом)
            $t->json('title')->nullable();          // {uk,ru,en}
            $t->unsignedInteger('price_uah');       // 119 / 189 / 249 ...
            $t->unsignedInteger('free_from')->nullable(); // порог для бесплатной доставки (если нужен)
            $t->string('color', 16)->default('#3b82f6');  // цвет заливки зоны

            $t->json('polygons'); // [[{lat:...,lng:...}, ...],  [...]]
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bs_delivery_zones'); }
};
