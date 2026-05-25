<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_locations', 'schedule_v2_enabled')) {
                $table->boolean('schedule_v2_enabled')->default(false)->after('schedule');
            }
            if (! Schema::hasColumn('bs_locations', 'schedule_v2')) {
                $table->json('schedule_v2')->nullable()->after('schedule_v2_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_locations', function (Blueprint $table) {
            if (Schema::hasColumn('bs_locations', 'schedule_v2')) {
                $table->dropColumn('schedule_v2');
            }
            if (Schema::hasColumn('bs_locations', 'schedule_v2_enabled')) {
                $table->dropColumn('schedule_v2_enabled');
            }
        });
    }
};
