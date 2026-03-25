<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'sales_receiving_delivery_time_analysis')
            ->first(['id', 'data_sources']);

        if (! $template) {
            return;
        }

        $dataSources = json_decode((string) $template->data_sources, true);
        if (! is_array($dataSources)) {
            return;
        }

        foreach ($dataSources as &$source) {
            if (! is_array($source) || (string) ($source['key'] ?? '') !== 'delivered') {
                continue;
            }

            $source['query'] = <<<'SQL'
SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN TIME(q.event_ts) >= '09:00:00' AND TIME(q.event_ts) <= '11:59:59' THEN 1 ELSE 0 END) AS slot_0900_1159,
    SUM(CASE WHEN TIME(q.event_ts) >= '12:00:00' AND TIME(q.event_ts) <= '14:00:00' THEN 1 ELSE 0 END) AS slot_1200_1400,
    SUM(CASE WHEN TIME(q.event_ts) >  '14:00:00' AND TIME(q.event_ts) <= '17:59:59' THEN 1 ELSE 0 END) AS slot_1401_1759,
    SUM(CASE WHEN TIME(q.event_ts) >= '18:00:00' AND TIME(q.event_ts) <= '20:00:00' THEN 1 ELSE 0 END) AS slot_1800_2000,
    SUM(CASE WHEN TIME(q.event_ts) <  '09:00:00' OR TIME(q.event_ts) > '20:00:00' THEN 1 ELSE 0 END) AS slot_other
FROM (
    SELECT
        COALESCE(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME),
            STR_TO_DATE(CONCAT(o.date_order, ' ', o.time_order), '%Y-%m-%d %H:%i:%s'),
            STR_TO_DATE(CONCAT(o.date_order, ' ', o.time_order), '%Y-%m-%d %H:%i'),
            STR_TO_DATE(CONCAT(o.dat, ' ', o.time_start), '%Y-%m-%d %H:%i:%s'),
            STR_TO_DATE(CONCAT(o.dat, ' ', o.time_start), '%Y-%m-%d %H:%i'),
            o.created_at
        ) AS event_ts
    FROM bs_shop_orders o
    WHERE o.deleted_at IS NULL
      AND o.status = 'delivered'
      AND DATE(
            COALESCE(
                CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME),
                STR_TO_DATE(CONCAT(o.date_order, ' ', o.time_order), '%Y-%m-%d %H:%i:%s'),
                STR_TO_DATE(CONCAT(o.date_order, ' ', o.time_order), '%Y-%m-%d %H:%i'),
                STR_TO_DATE(CONCAT(o.dat, ' ', o.time_start), '%Y-%m-%d %H:%i:%s'),
                STR_TO_DATE(CONCAT(o.dat, ' ', o.time_start), '%Y-%m-%d %H:%i'),
                o.created_at
            )
          ) BETWEEN :date_from AND :date_to
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) q
SQL;

            break;
        }

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
