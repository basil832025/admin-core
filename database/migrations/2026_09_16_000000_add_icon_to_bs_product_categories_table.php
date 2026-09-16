<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bs_product_categories', 'icon')) {
            Schema::table('bs_product_categories', function (Blueprint $table): void {
                $table->string('icon', 80)->nullable()->after('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bs_product_categories', 'icon')) {
            Schema::table('bs_product_categories', function (Blueprint $table): void {
                $table->dropColumn('icon');
            });
        }
    }
};
