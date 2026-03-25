<?php

namespace App\Reports\DataProviders;

use Illuminate\Support\Facades\DB;

class DeliveryZonesChartProvider implements ReportDataProviderInterface
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function resolve(array $params, array $context = []): array
    {
        $dateFrom = (string) ($params['date_from'] ?? '');
        $dateTo = (string) ($params['date_to'] ?? '');
        $brands = (string) ($params['brands'] ?? 'all');

        if ($dateFrom === '' || $dateTo === '') {
            return [
                'total' => 0,
                'values' => [],
                'colors' => [],
                'rows' => [],
            ];
        }

        $sql = <<<'SQL'
SELECT
    q.zone_name,
    q.zone_color,
    COUNT(*) AS deliveries_count,
    ROUND(SUM(q.order_sum), 2) AS orders_sum
FROM (
    SELECT
        COALESCE(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME),
            CASE
                WHEN o.date_order IS NOT NULL THEN CAST(CONCAT(o.date_order, ' 00:00:00') AS DATETIME)
                ELSE o.created_at
            END,
            o.created_at
        ) AS delivered_at,
        COALESCE(dz.name, 'Unknown') AS zone_name,
        COALESCE(dz.color, '#4f81bd') AS zone_color,
        COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0) AS order_sum
    FROM bs_shop_orders o
    LEFT JOIN bs_delivery_zones dz ON dz.id = o.delivery_zone_id
    WHERE o.deleted_at IS NULL
      AND o.self_pickup = 0
      AND o.status = 'delivered'
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) q
WHERE DATE(q.delivered_at) BETWEEN :date_from AND :date_to
GROUP BY q.zone_name, q.zone_color
ORDER BY deliveries_count DESC, q.zone_name ASC
SQL;

        $rows = DB::select($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'brands' => $brands,
        ]);

        $total = 0;
        foreach ($rows as $row) {
            $total += (int) ($row->deliveries_count ?? 0);
        }

        $values = [];
        $colors = [];
        $legendRows = [];

        foreach ($rows as $row) {
            $deliveries = max(0, (int) ($row->deliveries_count ?? 0));
            if ($deliveries <= 0) {
                continue;
            }

            $zoneName = (string) ($row->zone_name ?? 'Unknown');
            $zoneColor = (string) ($row->zone_color ?? '#4f81bd');
            $ordersSum = round((float) ($row->orders_sum ?? 0), 2);
            $percent = $total > 0 ? ($deliveries * 100 / $total) : 0;

            $values[] = $deliveries;
            $colors[] = $zoneColor;
            $legendRows[] = [
                'zone_name' => $zoneName,
                'zone_color' => $zoneColor,
                'deliveries_count' => $deliveries,
                'orders_sum' => $ordersSum,
                'percent' => round($percent, 2),
            ];
        }

        return [
            'total' => $total,
            'values' => $values,
            'colors' => $colors,
            'rows' => $legendRows,
        ];
    }
}
