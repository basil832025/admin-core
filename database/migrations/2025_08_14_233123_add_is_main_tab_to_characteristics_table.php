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
        Schema::table('characteristics', function (Blueprint $table) {
            // показывать на вкладке "Основные"
            $table->boolean('is_main_tab')
                ->default(false)
                ->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('characteristics', function (Blueprint $table) {
            $table->dropColumn('is_main_tab');
        });
    }
};
