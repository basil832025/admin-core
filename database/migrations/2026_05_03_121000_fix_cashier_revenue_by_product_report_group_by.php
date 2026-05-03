<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'data_sources']);

        if (! $template) {
            return;
        }

        $sources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($sources)) {
            $sources = [];
        }

        $query = <<<'SQL'
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
    SUM(COALESCE(i.qty, 0)) AS qty,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0)), 2) AS gross,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS discount,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS bonuses_spent,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_accrued, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS bonuses_accrued,
    ROUND(
        SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0))
        - SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)))
        - SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))),
        2
    ) AS revenue
FROM bs_shop_orders o
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
  AND o.status = 'delivered'
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN COALESCE(NULLIF(:date_from, ''), CURDATE()) AND COALESCE(NULLIF(:date_to, ''), CURDATE())
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY
    COALESCE(NULLIF(i.sku, ''), CAST(i.product_id AS CHAR)),
    COALESCE(
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(i.product_snapshot, '$.title')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."uk"')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."ru"')), ''),
        p.slug,
        CONCAT('product#', p.id)
    ),
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
    END
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
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
