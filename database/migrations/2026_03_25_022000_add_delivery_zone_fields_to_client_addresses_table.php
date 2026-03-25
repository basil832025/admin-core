<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_client_addresses', 'delivery_zone_id')) {
                $table->foreignId('delivery_zone_id')
                    ->nullable()
                    ->after('formatted_address')
                    ->constrained('bs_delivery_zones')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('bs_client_addresses', 'zone_resolution_method')) {
                $table->string('zone_resolution_method', 40)
                    ->nullable()
                    ->after('delivery_zone_id');
            }

            if (! Schema::hasColumn('bs_client_addresses', 'zone_resolved_at')) {
                $table->dateTime('zone_resolved_at')
                    ->nullable()
                    ->after('zone_resolution_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_client_addresses', 'zone_resolved_at')) {
                $table->dropColumn('zone_resolved_at');
            }

            if (Schema::hasColumn('bs_client_addresses', 'zone_resolution_method')) {
                $table->dropColumn('zone_resolution_method');
            }

            if (Schema::hasColumn('bs_client_addresses', 'delivery_zone_id')) {
                $table->dropConstrainedForeignId('delivery_zone_id');
            }
        });
    }
};
