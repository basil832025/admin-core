<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            $table->decimal('price_unit_quantity', 10, 3)
                ->default(1)
                ->after('unit_id');
        });

        DB::table('bs_products')
            ->whereNull('price_unit_quantity')
            ->orWhere('price_unit_quantity', '<=', 0)
            ->update(['price_unit_quantity' => 1]);
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            $table->dropColumn('price_unit_quantity');
        });
    }
};
