<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_client_addresses', 'bring_to_floor')) {
                $table->boolean('bring_to_floor')->default(false)->after('entrance');
            }

            if (! Schema::hasColumn('bs_client_addresses', 'elevator')) {
                $table->string('elevator', 20)->nullable()->after('bring_to_floor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_client_addresses', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_client_addresses', 'elevator')) {
                $table->dropColumn('elevator');
            }

            if (Schema::hasColumn('bs_client_addresses', 'bring_to_floor')) {
                $table->dropColumn('bring_to_floor');
            }
        });
    }
};
