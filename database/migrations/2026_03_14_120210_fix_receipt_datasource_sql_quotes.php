<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sources = [
            [
                'key' => 'order',
                'type' => 'sql',
                'enabled' => true,
                'query' => 'SELECT o.id, COALESCE(o.number, o.id) AS order_number, o.total_price_sale, o.total_price, o.self_pickup, o.kitchen_note, o.courier_comment, o.date_order, o.time_order, c.name AS client_name, c.phone, TRIM(CONCAT_WS(\', \', NULLIF(ca.street, \'\'), NULLIF(ca.house, \'\'), NULLIF(ca.apartment, \'\'), NULLIF(ca.city, \'\'))) AS address_line FROM bs_shop_orders o LEFT JOIN bs_clients c ON c.id = o.clients_id LEFT JOIN bs_client_addresses ca ON ca.id = o.client_address_id WHERE o.id = :order_id LIMIT 1',
            ],
            [
                'key' => 'items',
                'type' => 'sql',
                'enabled' => true,
                'query' => 'SELECT oi.id, oi.qty, oi.sku, oi.total, oi.kitchen_note, p.short_name AS product_name FROM bs_shop_order_items oi LEFT JOIN bs_products p ON p.id = oi.product_id WHERE oi.shop_order_id = :order_id ORDER BY oi.id ASC',
            ],
        ];

        DB::table('bs_print_templates')
            ->whereIn('code', ['receipt_kitchen_default', 'receipt_client_default', 'receipt_logistic_default'])
            ->update([
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
