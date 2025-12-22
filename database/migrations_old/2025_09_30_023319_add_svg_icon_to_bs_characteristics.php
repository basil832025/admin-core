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
        Schema::table('bs_characteristics', function (Blueprint $table) {
            $table->foreignId('svg_image_id')
                ->nullable()
                ->after('slug')
                ->constrained('bs_svg_images') // FK на таблицу с SVG
                ->nullOnDelete();
            $table->index('svg_image_id', 'idx_characteristics_svg_image');
        });
    }

    public function down(): void
    {
        Schema::table('bs_characteristics', function (Blueprint $table) {
            $table->dropIndex('idx_characteristics_svg_image');
            $table->dropConstrainedForeignId('svg_image_id');
        });
    }
};
