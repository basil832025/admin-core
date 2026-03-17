<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->string('editor_mode', 24)->default('code')->after('description');
            $table->json('editor_meta')->nullable()->after('editor_mode');
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->dropColumn(['editor_mode', 'editor_meta']);
        });
    }
};
