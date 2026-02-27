<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_order_items', 'kitchen_note')) {
                $table->text('kitchen_note')->nullable()->after('stage_flags');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_order_items', 'kitchen_note')) {
                $table->dropColumn('kitchen_note');
            }
        });
    }
};
