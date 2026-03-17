<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_print_operation_profiles', function (Blueprint $table): void {
            $table->json('param_bindings')->nullable()->after('paper_settings');
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_operation_profiles', function (Blueprint $table): void {
            $table->dropColumn('param_bindings');
        });
    }
};
