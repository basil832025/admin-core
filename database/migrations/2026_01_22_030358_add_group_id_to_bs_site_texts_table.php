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
        Schema::table('bs_site_texts', function (Blueprint $table) {
            if (!Schema::hasColumn('bs_site_texts', 'group_id')) {
                $table->unsignedBigInteger('group_id')
                    ->nullable()
                    ->index()
                    ->after('group');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_site_texts', function (Blueprint $table) {
            if (Schema::hasColumn('bs_site_texts', 'group_id')) {
                $table->dropIndex(['group_id']);
                $table->dropColumn('group_id');
            }
        });
    }
};
