<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->string('fulfillment_method', 16)
                ->nullable()
                ->after('per_client_limit')
                ->index('spc_fulfillment_method_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->dropIndex('spc_fulfillment_method_idx');
            $table->dropColumn('fulfillment_method');
        });
    }
};