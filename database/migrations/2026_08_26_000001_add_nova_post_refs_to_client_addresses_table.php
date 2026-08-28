<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_client_addresses', 'nova_city_ref')) {
                $table->string('nova_city_ref', 80)->nullable()->after('city');
            }

            if (! Schema::hasColumn('bs_client_addresses', 'nova_warehouse_ref')) {
                $table->string('nova_warehouse_ref', 80)->nullable()->after('formatted_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_client_addresses', 'nova_warehouse_ref')) {
                $table->dropColumn('nova_warehouse_ref');
            }

            if (Schema::hasColumn('bs_client_addresses', 'nova_city_ref')) {
                $table->dropColumn('nova_city_ref');
            }
        });
    }
};
