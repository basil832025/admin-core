<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->string('default_paper_preset', 32)->default('a4')->after('output_format');
            $table->decimal('default_paper_width_mm', 8, 2)->nullable()->after('default_paper_preset');
            $table->decimal('default_paper_height_mm', 8, 2)->nullable()->after('default_paper_width_mm');
        });
    }

    public function down(): void
    {
        Schema::table('bs_print_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'default_paper_preset',
                'default_paper_width_mm',
                'default_paper_height_mm',
            ]);
        });
    }
};
