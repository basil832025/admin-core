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
        Schema::table('bs_pages', function (Blueprint $table) {
            $table->json('fields')->nullable()->after('content'); // или после любого подходящего поля
        });
    }

    public function down(): void
    {
        Schema::table('bs_pages', function (Blueprint $table) {
            $table->dropColumn('fields');
        });
    }
};
