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
        Schema::table('bs_svg_images', function (Blueprint $table) {
            $table->boolean('is_attr')->default(false)->after('description');
            $table->index('is_attr', 'idx_svg_images_is_attr');
        });
    }

    public function down(): void
    {
        Schema::table('bs_svg_images', function (Blueprint $table) {
            $table->dropIndex('idx_svg_images_is_attr');
            $table->dropColumn('is_attr');
        });
    }
};
