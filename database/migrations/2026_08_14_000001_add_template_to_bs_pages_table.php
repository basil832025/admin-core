<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('bs_pages', 'template')) {
                $table->string('template')->nullable()->after('slug')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_pages', function (Blueprint $table) {
            if (Schema::hasColumn('bs_pages', 'template')) {
                $table->dropColumn('template');
            }
        });
    }
};
