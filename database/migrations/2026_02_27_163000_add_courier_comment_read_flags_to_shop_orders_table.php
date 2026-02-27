<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('bs_shop_orders', 'courier_comment_changed_at')) {
                $table->timestamp('courier_comment_changed_at')->nullable()->after('courier_comment');
            }

            if (! Schema::hasColumn('bs_shop_orders', 'courier_comment_read_at')) {
                $table->timestamp('courier_comment_read_at')->nullable()->after('courier_comment_changed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('bs_shop_orders', 'courier_comment_read_at')) {
                $table->dropColumn('courier_comment_read_at');
            }

            if (Schema::hasColumn('bs_shop_orders', 'courier_comment_changed_at')) {
                $table->dropColumn('courier_comment_changed_at');
            }
        });
    }
};
