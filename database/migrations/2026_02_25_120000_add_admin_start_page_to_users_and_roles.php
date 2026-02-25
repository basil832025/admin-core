<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'admin_start_page')) {
                $table->string('admin_start_page', 64)->nullable()->after('position_id');
            }
        });

        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'admin_start_page')) {
                $table->string('admin_start_page', 64)->nullable()->after('guard_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'admin_start_page')) {
                $table->dropColumn('admin_start_page');
            }
        });

        Schema::table('roles', function (Blueprint $table): void {
            if (Schema::hasColumn('roles', 'admin_start_page')) {
                $table->dropColumn('admin_start_page');
            }
        });
    }
};
