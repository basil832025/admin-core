<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_product_price_change_batches', function (Blueprint $table): void {
            $table->unsignedTinyInteger('rounding_precision')->default(0)->after('old_price_mode');
        });
    }

    public function down(): void
    {
        Schema::table('bs_product_price_change_batches', function (Blueprint $table): void {
            $table->dropColumn('rounding_precision');
        });
    }
};
