<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_products', 'exclude_from_promotions')) {
                $table->boolean('exclude_from_promotions')
                    ->default(false)
                    ->index()
                    ->after('is_spicy');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_products', 'exclude_from_promotions')) {
                $table->dropColumn('exclude_from_promotions');
            }
        });
    }
};
