<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->string('css_preset', 64)->default('none')->after('editor_meta');
            $table->longText('custom_css')->nullable()->after('css_preset');
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->dropColumn(['css_preset', 'custom_css']);
        });
    }
};
