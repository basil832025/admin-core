<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->string('discount_type', 16)
                ->default('percent')
                ->after('code');

            $table->decimal('amount', 12, 2)
                ->nullable()
                ->after('percent');
        });

        DB::table('bs_shop_promo_codes')
            ->whereNull('discount_type')
            ->update(['discount_type' => 'percent']);
    }

    public function down(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'amount']);
        });
    }
};
