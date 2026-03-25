<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dataSources = [
            [
                'key' => 'summary',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check,
    ROUND(MIN(CASE WHEN COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0) > 0 THEN COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0) END), 2) AS min_check,
    ROUND(MAX(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS max_check
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
SQL,
            ],
            [
                'key' => 'by_source',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    COALESCE(CAST(o.source_id AS CHAR), 'local') AS source_key,
    COALESCE(s.name, '3 Пирога (local)') AS source_name,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check
FROM bs_shop_orders o
LEFT JOIN bs_cc_sources s ON s.id = o.source_id
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
GROUP BY source_key, source_name
ORDER BY avg_check DESC
SQL,
            ],
            [
                'key' => 'by_day',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    DATE(COALESCE(o.date_order, o.created_at)) AS report_date,
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
GROUP BY report_date
ORDER BY report_date ASC
SQL,
            ],
        ];

        DB::table('bs_print_templates')
            ->where('code', 'sales_average_check')
            ->update([
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $dataSources = [
            [
                'key' => 'summary',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check,
    ROUND(MIN(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS min_check,
    ROUND(MAX(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS max_check
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
SQL,
            ],
            [
                'key' => 'by_source',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    COALESCE(CAST(o.source_id AS CHAR), 'local') AS source_key,
    COALESCE(s.name, '3 Пирога (local)') AS source_name,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check
FROM bs_shop_orders o
LEFT JOIN bs_cc_sources s ON s.id = o.source_id
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
GROUP BY source_key, source_name
ORDER BY avg_check DESC
SQL,
            ],
            [
                'key' => 'by_day',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    DATE(COALESCE(o.date_order, o.created_at)) AS report_date,
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
GROUP BY report_date
ORDER BY report_date ASC
SQL,
            ],
        ];

        DB::table('bs_print_templates')
            ->where('code', 'sales_average_check')
            ->update([
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
