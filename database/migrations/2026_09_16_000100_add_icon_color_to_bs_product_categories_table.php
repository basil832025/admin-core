<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bs_product_categories', 'icon_color')) {
            Schema::table('bs_product_categories', function (Blueprint $table): void {
                $table->string('icon_color', 7)->nullable()->after('icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bs_product_categories', 'icon_color')) {
            Schema::table('bs_product_categories', function (Blueprint $table): void {
                $table->dropColumn('icon_color');
            });
        }
    }
};
