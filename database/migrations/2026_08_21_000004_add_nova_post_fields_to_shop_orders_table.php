<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            $fields = [
                'nova_delivery_type' => fn () => $table->string('nova_delivery_type', 32)->nullable()->after('shipping_method'),
                'nova_city' => fn () => $table->string('nova_city')->nullable()->after('nova_delivery_type'),
                'nova_city_ref' => fn () => $table->string('nova_city_ref', 80)->nullable()->after('nova_city'),
                'nova_warehouse' => fn () => $table->string('nova_warehouse')->nullable()->after('nova_city_ref'),
                'nova_warehouse_ref' => fn () => $table->string('nova_warehouse_ref', 80)->nullable()->after('nova_warehouse'),
                'nova_ttn' => fn () => $table->string('nova_ttn', 32)->nullable()->after('nova_warehouse_ref'),
                'nova_status' => fn () => $table->string('nova_status', 80)->nullable()->after('nova_ttn'),
                'nova_payer' => fn () => $table->string('nova_payer', 32)->nullable()->after('nova_status'),
                'nova_declared_value' => fn () => $table->decimal('nova_declared_value', 12, 2)->nullable()->after('nova_payer'),
                'nova_cod_amount' => fn () => $table->decimal('nova_cod_amount', 12, 2)->nullable()->after('nova_declared_value'),
                'nova_ttn_created_at' => fn () => $table->timestamp('nova_ttn_created_at')->nullable()->after('nova_cod_amount'),
                'nova_tariff_type' => fn () => $table->string('nova_tariff_type', 80)->nullable()->after('nova_ttn_created_at'),
            ];

            foreach ($fields as $column => $definition) {
                if (! Schema::hasColumn('bs_shop_orders', $column)) {
                    $definition();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bs_shop_orders', function (Blueprint $table): void {
            foreach ([
                'nova_tariff_type',
                'nova_ttn_created_at',
                'nova_cod_amount',
                'nova_declared_value',
                'nova_payer',
                'nova_status',
                'nova_ttn',
                'nova_warehouse_ref',
                'nova_warehouse',
                'nova_city_ref',
                'nova_city',
                'nova_delivery_type',
            ] as $column) {
                if (Schema::hasColumn('bs_shop_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
