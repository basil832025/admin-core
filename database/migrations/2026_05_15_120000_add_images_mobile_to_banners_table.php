<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_banners', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_banners', 'images_mobile')) {
                $table->json('images_mobile')->nullable()->after('image_mobile');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_banners', function (Blueprint $table) {
            if (Schema::hasColumn('bs_banners', 'images_mobile')) {
                $table->dropColumn('images_mobile');
            }
        });
    }
};
