<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->foreignId('gift_product_id')->nullable()->constrained('bs_products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_promo_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_product_id');
        });
    }
};
