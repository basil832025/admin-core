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
        Schema::table('bs_blogs', function (Blueprint $table) {
            // оставить старые поля как есть (VARCHAR) — это дефолт
            // добавить JSON-оверлей по языкам
            $table->json('preview_image_i18n')->nullable()->after('preview_image');
            $table->json('detail_image_i18n')->nullable()->after('detail_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bs_blogs', function (Blueprint $table) {
            //
        });
    }
};
