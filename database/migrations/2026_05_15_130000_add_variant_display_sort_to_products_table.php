<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            $table->integer('variant_display_sort')
                ->nullable()
                ->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            $table->dropColumn('variant_display_sort');
        });
    }
};
