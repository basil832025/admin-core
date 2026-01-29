<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_locations', function (Blueprint $table) {
            // Текстовое поле для хранения ссылки на точку в Google Maps
            $table->string('google_map_link')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('bs_locations', function (Blueprint $table) {
            $table->dropColumn('google_map_link');
        });
    }
};

