<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'parameters_schema', 'data_sources']);

        if (! $template) {
            return;
        }

        $schema = json_decode((string) ($template->parameters_schema ?? ''), true);
        if (! is_array($schema)) {
            $schema = [];
        }

        // Remove technical param and ensure status_filter options are present.
        $newSchema = [];
        foreach ($schema as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = (string) ($item['key'] ?? '');
            if ($key === 'status_filter2') {
                continue;
            }

            if ($key === 'status_filter') {
                $item['type'] = 'select';
                $item['label'] = $item['label'] ?? 'Статус заказов';
                $item['default'] = $item['default'] ?? 'delivered';
                $item['required'] = true;
                $item['options'] = [
                    'delivered' => 'Доставлено (по умолчанию)',
                    'all_active' => 'Все (кроме корзины и отмененных)',
                    'new' => 'Новые',
                    'processing' => 'В обработке',
                    'on_hold' => 'Отложенные',
                    'filling' => 'Начинка',
                    'molding' => 'Лепка',
                    'baking' => 'Печь',
                    'prepared' => 'Приготовлен',
                    'assembled' => 'Собран',
                    'shipped' => 'В пути',
                    'cancelled' => 'Отмененные',
                ];
            }

            $newSchema[] = $item;
        }

        // If status_filter was not present, add it.
        $hasStatus = false;
        foreach ($newSchema as $item) {
            if (is_array($item) && (string) ($item['key'] ?? '') === 'status_filter') {
                $hasStatus = true;
                break;
            }
        }
        if (! $hasStatus) {
            $newSchema[] = [
                'key' => 'status_filter',
                'type' => 'select',
                'label' => 'Статус заказов',
                'default' => 'delivered',
                'required' => true,
                'options' => [
                    'delivered' => 'Доставлено (по умолчанию)',
                    'all_active' => 'Все (кроме корзины и отмененных)',
                    'new' => 'Новые',
                    'processing' => 'В обработке',
                    'on_hold' => 'Отложенные',
                    'filling' => 'Начинка',
                    'molding' => 'Лепка',
                    'baking' => 'Печь',
                    'prepared' => 'Приготовлен',
                    'assembled' => 'Собран',
                    'shipped' => 'В пути',
                    'cancelled' => 'Отмененные',
                ],
            ];
        }

        $sources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($sources)) {
            $sources = [];
        }

        $query = <<<'SQL'
SELECT
    t.sku,
    t.title,
    SUM(t.qty) AS qty,
    ROUND(SUM(t.gross), 2) AS gross,
    ROUND(SUM(t.discount), 2) AS discount,
    ROUND(SUM(t.bonuses_spent), 2) AS bonuses_spent,
    ROUND(SUM(t.bonuses_accrued), 2) AS bonuses_accrued,
    ROUND(SUM(t.revenue), 2) AS revenue,

    ROUND(SUM(CASE WHEN t.payment_code = 'CASH' THEN t.revenue ELSE 0 END), 2) AS pay_CASH,
    ROUND(SUM(CASE WHEN t.payment_code = 'CARD' THEN t.revenue ELSE 0 END), 2) AS pay_CARD,
    ROUND(SUM(CASE WHEN t.payment_code = 'POS' THEN t.revenue ELSE 0 END), 2) AS pay_POS,
    ROUND(SUM(CASE WHEN t.payment_code = 'LIQPAY' THEN t.revenue ELSE 0 END), 2) AS pay_LIQPAY,
    ROUND(SUM(CASE WHEN t.payment_code = 'INVOICE' THEN t.revenue ELSE 0 END), 2) AS pay_INVOICE,
    ROUND(SUM(CASE WHEN t.payment_code = 'ORG' THEN t.revenue ELSE 0 END), 2) AS pay_ORG,
    ROUND(SUM(CASE WHEN t.payment_code = 'CLUB' THEN t.revenue ELSE 0 END), 2) AS pay_CLUB,
    ROUND(SUM(CASE WHEN t.payment_code = 'FREE' THEN t.revenue ELSE 0 END), 2) AS pay_FREE,
    ROUND(SUM(CASE WHEN t.payment_code = 'OTHER' THEN t.revenue ELSE 0 END), 2) AS pay_OTHER
FROM (
    SELECT
        COALESCE(NULLIF(i.sku, ''), CAST(i.product_id AS CHAR)) AS sku,
        COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(i.product_snapshot, '$.title')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."uk"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."ru"')), ''),
            p.slug,
            CONCAT('product#', p.id)
        ) AS title,
        CASE COALESCE(o.payment, 0)
            WHEN 1 THEN 'CARD'
            WHEN 2 THEN 'CASH'
            WHEN 3 THEN 'CLUB'
            WHEN 4 THEN 'ORG'
            WHEN 5 THEN 'FREE'
            WHEN 9 THEN 'POS'
            WHEN 10 THEN 'INVOICE'
            WHEN 11 THEN 'LIQPAY'
            ELSE 'OTHER'
        END AS payment_code,
        COALESCE(i.qty, 0) AS qty,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) AS gross,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS discount,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS bonuses_spent,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_accrued, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS bonuses_accrued,
        (
            COALESCE(i.subtotal, i.unit_price * i.qty, 0)
            - COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))
            - COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))
        ) AS revenue
    FROM bs_shop_orders o
    CROSS JOIN (
        SELECT COALESCE(NULLIF(:status_filter, ''), 'delivered') AS sf
    ) prm
    JOIN bs_shop_order_items i ON i.shop_order_id = o.id
    LEFT JOIN bs_products p ON p.id = i.product_id
    LEFT JOIN (
        SELECT
            a.shop_order_id,
            ABS(SUM(a.amount)) AS discount_abs
        FROM bs_shop_order_adjustments a
        WHERE a.shop_order_item_id IS NULL
          AND a.amount < 0
          AND a.type NOT IN ('loyalty', 'loyalty_spent', 'bonus_spent')
        GROUP BY a.shop_order_id
    ) od ON od.shop_order_id = o.id
    LEFT JOIN (
        SELECT
            lt.order_id,
            ABS(SUM(CASE WHEN lt.type = 'spend' THEN lt.amount ELSE 0 END)) AS bonuses_spent_abs,
            SUM(CASE WHEN lt.type = 'accrual' AND COALESCE(lt.source, '') = 'order' THEN lt.amount ELSE 0 END) AS bonuses_accrued
        FROM bs_loyalty_transactions lt
        GROUP BY lt.order_id
    ) ob ON ob.order_id = o.id
    WHERE o.deleted_at IS NULL
      AND o.status <> 'cart'
      AND (
            (prm.sf = 'all_active' AND o.status <> 'cancelled')
            OR (prm.sf <> 'all_active' AND o.status = prm.sf)
          )
      AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN DATE(COALESCE(NULLIF(:date_from, ''), CURDATE())) AND DATE(COALESCE(NULLIF(:date_to, ''), CURDATE()))
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) t
GROUP BY t.sku, t.title
ORDER BY revenue DESC, title ASC
SQL;

        foreach ($sources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if ((string) ($source['key'] ?? '') === 'main' && (string) ($source['type'] ?? '') === 'sql') {
                $source['query'] = $query;
            }
        }
        unset($source);

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'parameters_schema' => json_encode($newSchema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
