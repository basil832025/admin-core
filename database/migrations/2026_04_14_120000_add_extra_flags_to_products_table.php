<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_products', 'is_promo')) {
                $table->boolean('is_promo')->default(false)->after('is_home');
            }

            if (! Schema::hasColumn('bs_products', 'is_vegan')) {
                $table->boolean('is_vegan')->default(false)->after('is_promo');
            }

            if (! Schema::hasColumn('bs_products', 'is_product_of_day')) {
                $table->boolean('is_product_of_day')->default(false)->after('is_vegan');
            }

            if (! Schema::hasColumn('bs_products', 'is_spicy')) {
                $table->boolean('is_spicy')->default(false)->after('is_product_of_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_products', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_products', 'is_spicy')) {
                $table->dropColumn('is_spicy');
            }

            if (Schema::hasColumn('bs_products', 'is_product_of_day')) {
                $table->dropColumn('is_product_of_day');
            }

            if (Schema::hasColumn('bs_products', 'is_vegan')) {
                $table->dropColumn('is_vegan');
            }

            if (Schema::hasColumn('bs_products', 'is_promo')) {
                $table->dropColumn('is_promo');
            }
        });
    }
};
