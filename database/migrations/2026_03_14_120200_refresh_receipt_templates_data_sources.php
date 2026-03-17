<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $commonSources = [
            [
                'key' => 'order',
                'type' => 'sql',
                'enabled' => true,
                'query' => 'SELECT o.id, COALESCE(o.number, o.id) AS order_number, o.total_price_sale, o.total_price, o.self_pickup, o.kitchen_note, o.courier_comment, o.date_order, o.time_order, c.name AS client_name, c.phone, TRIM(CONCAT_WS(', ', NULLIF(ca.street, \'\'), NULLIF(ca.house, \'\'), NULLIF(ca.apartment, \'\'), NULLIF(ca.city, \'\'))) AS address_line FROM bs_shop_orders o LEFT JOIN bs_clients c ON c.id = o.clients_id LEFT JOIN bs_client_addresses ca ON ca.id = o.client_address_id WHERE o.id = :order_id LIMIT 1',
            ],
            [
                'key' => 'items',
                'type' => 'sql',
                'enabled' => true,
                'query' => 'SELECT oi.id, oi.qty, oi.sku, oi.total, oi.kitchen_note, p.short_name AS product_name FROM bs_shop_order_items oi LEFT JOIN bs_products p ON p.id = oi.product_id WHERE oi.shop_order_id = :order_id ORDER BY oi.id ASC',
            ],
        ];

        $updates = [
            'receipt_kitchen_default' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">{{ kitchen_header|default("Робочий чек кухні") }}</div>'
                . '{% set order = datasets.order is defined and datasets.order|length ? datasets.order[0] : {} %}'
                . '<div><strong>Замовлення №:</strong> {{ order.order_number|default(order_number|default("-")) }}</div>'
                . '<div><strong>Оператор:</strong> {{ operator|default("-") }}</div>'
                . '<div><strong>Друк:</strong> {{ printed_at|default("-") }}</div>'
                . '<div><strong>Телефон:</strong> {{ order.phone|default(phone|default("-")) }}</div>'
                . '<div><strong>Доставка:</strong> {{ delivery_time|default((order.date_order|default("")) ~ " " ~ (order.time_order|default(""))) }}</div>'
                . '<div><strong>Адреса:</strong> {{ order.address_line|default(address|default("-")) }}</div>'
                . '<div><strong>Коментар:</strong> {{ order.kitchen_note|default(note|default("-")) }}</div>'
                . '<hr>'
                . '{% if datasets.items is defined and datasets.items|length %}{% for item in datasets.items %}<div><strong>{{ item.qty|default(0) }} x </strong>{{ item.product_name|default("Товар") }}</div>{% if item.kitchen_note is defined and item.kitchen_note %}<div style="padding-left:3mm;">* {{ item.kitchen_note }}</div>{% endif %}{% endfor %}{% elseif items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                . '<hr>'
                . '<div style="font-size:13pt;font-weight:700;">Сума: {{ order.total_price_sale|default(order.total_price|default(total|default("-"))) }}</div>',
            'receipt_client_default' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:1mm;">ЧЕК ДЛЯ КЛІЄНТА</div>'
                . '{% if client_logo is defined %}{{ client_logo|raw }}{% endif %}'
                . '{% set order = datasets.order is defined and datasets.order|length ? datasets.order[0] : {} %}'
                . '<div><strong>Замовлення №:</strong> {{ order.order_number|default(order_number|default("-")) }}</div>'
                . '<div><strong>Друк:</strong> {{ printed_at|default("-") }}</div>'
                . '<div><strong>Телефон:</strong> {{ order.phone|default(phone|default("-")) }}</div>'
                . '<div><strong>Час доставки:</strong> {{ delivery_time|default((order.date_order|default("")) ~ " " ~ (order.time_order|default(""))) }}</div>'
                . '<div><strong>Адреса:</strong> {{ order.address_line|default(address|default("-")) }}</div>'
                . '<hr>'
                . '{% if datasets.items is defined and datasets.items|length %}{% for item in datasets.items %}<div><strong>{{ item.qty|default(0) }} x </strong>{{ item.product_name|default("Товар") }}</div>{% endfor %}{% elseif items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                . '<hr>'
                . '<div style="font-size:13pt;font-weight:700;">До сплати: {{ order.total_price_sale|default(order.total_price|default(total|default("-"))) }}</div>'
                . '<div style="text-align:center;margin-top:2mm;">Дякуємо за замовлення</div>',
            'receipt_logistic_default' => '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">СЛУЖБОВИЙ ЧЕК ЛОГІСТА</div>'
                . '{% set order = datasets.order is defined and datasets.order|length ? datasets.order[0] : {} %}'
                . '<div><strong>Замовлення №:</strong> {{ order.order_number|default(order_number|default("-")) }}</div>'
                . '<div><strong>Оператор:</strong> {{ operator|default("-") }}</div>'
                . '<div><strong>Клієнт:</strong> {{ order.client_name|default(client_name|default("-")) }}</div>'
                . '<div><strong>Телефон:</strong> {{ order.phone|default(phone|default("-")) }}</div>'
                . '<div><strong>Доставка:</strong> {{ delivery_time|default((order.date_order|default("")) ~ " " ~ (order.time_order|default(""))) }}</div>'
                . '<div><strong>Адреса:</strong> {{ order.address_line|default(address|default("-")) }}</div>'
                . '<div><strong>Коментар:</strong> {{ order.courier_comment|default(note|default("-")) }}</div>'
                . '<hr>'
                . '{% if datasets.items is defined and datasets.items|length %}{% for item in datasets.items %}<div><strong>{{ item.qty|default(0) }} x </strong>{{ item.product_name|default("Товар") }}</div>{% endfor %}{% elseif items_html is defined and items_html %}{{ items_html|raw }}{% else %}<div>{{ items|default("")|nl2br }}</div>{% endif %}'
                . '<hr>'
                . '<div style="font-size:13pt;font-weight:700;">До сплати: {{ order.total_price_sale|default(order.total_price|default(total|default("-"))) }}</div>',
        ];

        foreach ($updates as $code => $body) {
            DB::table('bs_print_templates')
                ->where('code', $code)
                ->update([
                    'data_sources' => json_encode($commonSources, JSON_UNESCAPED_UNICODE),
                    'template_body' => $body,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};
