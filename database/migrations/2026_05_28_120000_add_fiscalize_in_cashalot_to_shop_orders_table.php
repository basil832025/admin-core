<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            $table->boolean('fiscalize_in_cashalot')
                ->default(false)
                ->after('payment')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            $table->dropColumn('fiscalize_in_cashalot');
        });
    }
};
