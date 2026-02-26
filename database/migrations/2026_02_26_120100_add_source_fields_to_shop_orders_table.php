<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->after('id')->constrained('bs_cc_sources')->nullOnDelete();
            $table->string('external_order_id', 64)->nullable()->after('source_id');
            $table->boolean('has_unmatched_items')->default(false)->after('external_order_id');
            $table->timestamp('synced_at')->nullable()->after('has_unmatched_items');

            $table->unique(['source_id', 'external_order_id'], 'bs_shop_orders_source_external_unique');
            $table->index('source_id', 'bs_shop_orders_source_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table) {
            $table->dropUnique('bs_shop_orders_source_external_unique');
            $table->dropIndex('bs_shop_orders_source_idx');
            $table->dropConstrainedForeignId('source_id');
            $table->dropColumn(['external_order_id', 'has_unmatched_items', 'synced_at']);
        });
    }
};
