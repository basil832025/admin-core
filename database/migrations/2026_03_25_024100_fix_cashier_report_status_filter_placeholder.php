<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_daily_revenue_by_payment')
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
    DATE(COALESCE(o.date_order, o.created_at)) AS report_date,
    CASE COALESCE(o.payment, 0)
        WHEN 1 THEN 'Банковская карта'
        WHEN 2 THEN 'Наличные'
        WHEN 3 THEN 'Клубная карта'
        WHEN 4 THEN 'Безналичный через организацию'
        WHEN 5 THEN 'Без оплаты'
        WHEN 9 THEN 'POS-терминал'
        WHEN 10 THEN 'Счет-фактура'
        WHEN 11 THEN 'LiqPay'
        ELSE 'Не указан'
    END AS payment_method,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
  AND o.status = COALESCE(NULLIF(:status_filter, 'all_active'), o.status)
GROUP BY report_date, payment_method
ORDER BY report_date ASC, revenue_total DESC, payment_method ASC
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
