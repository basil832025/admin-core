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
        Schema::table('bs_svg_images', function (Blueprint $table) {
            $table->string('default_color', 16)->nullable()->after('svg_code');   // например "#111827"
            $table->json('color_variants')->nullable()->after('default_color');   // ["#111827","#FF7500","#EF4444"]
        });
    }

    public function down(): void
    {
        Schema::table('bs_svg_images', function (Blueprint $table) {
            $table->dropColumn(['default_color', 'color_variants']);
        });
    }
};
