<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'kitchen_note')) {
                $table->text('kitchen_note')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'courier_comment')) {
                $table->text('courier_comment')->nullable()->after('kitchen_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'courier_comment')) {
                $table->dropColumn('courier_comment');
            }

            if (Schema::hasColumn('bs_shop_orders', 'kitchen_note')) {
                $table->dropColumn('kitchen_note');
            }
        });
    }
};
