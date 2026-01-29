<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->string('street_place_id')->nullable()->after('longitude');
            $table->string('formatted_address')->nullable()->after('street_place_id');

            $table->index(['latitude', 'longitude']);
            $table->index('street_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropIndex(['street_place_id']);

            $table->dropColumn(['latitude', 'longitude', 'street_place_id', 'formatted_address']);
        });
    }
};
