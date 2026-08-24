<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'nova_city_details')) {
                $table->string('nova_city_details')->nullable()->after('nova_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'nova_city_details')) {
                $table->dropColumn('nova_city_details');
            }
        });
    }
};
