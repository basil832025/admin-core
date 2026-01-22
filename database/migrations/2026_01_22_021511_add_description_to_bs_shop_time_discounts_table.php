<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            // JSON чтобы было переводимое поле (Spatie Translatable)
            $table->json('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_time_discounts', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
