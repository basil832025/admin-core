<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'confirm_without_call')) {
                $table->boolean('confirm_without_call')
                    ->default(false)
                    ->after('recipient_phone');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'gift_no_receipt')) {
                $table->boolean('gift_no_receipt')
                    ->default(false)
                    ->after('confirm_without_call');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            foreach (['gift_no_receipt', 'confirm_without_call'] as $column) {
                if (Schema::hasColumn('bs_shop_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
