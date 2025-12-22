<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_banners', function (Blueprint $table) {
            // JSON с массивом [{locale: "uk", path: "..."}, ...]
            $table->json('images')->nullable()->after('image');

        });
    }

    public function down(): void
    {
        Schema::table('bs_banners', function (Blueprint $table) {
           $table->dropColumn('images');
        });
    }
};
