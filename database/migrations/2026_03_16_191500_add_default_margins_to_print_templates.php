<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->decimal('default_margin_top_mm', 8, 2)->nullable()->after('default_paper_height_mm');
            $table->decimal('default_margin_right_mm', 8, 2)->nullable()->after('default_margin_top_mm');
            $table->decimal('default_margin_bottom_mm', 8, 2)->nullable()->after('default_margin_right_mm');
            $table->decimal('default_margin_left_mm', 8, 2)->nullable()->after('default_margin_bottom_mm');
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'default_margin_top_mm',
                'default_margin_right_mm',
                'default_margin_bottom_mm',
                'default_margin_left_mm',
            ]);
        });
    }
};
