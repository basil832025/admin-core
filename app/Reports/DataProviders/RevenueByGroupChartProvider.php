<?php

namespace App\Reports\DataProviders;

use Illuminate\Support\Facades\DB;

class RevenueByGroupChartProvider implements ReportDataProviderInterface
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
                'total' => 0.0,
                'labels' => [],
                'values' => [],
                'colors' => [],
                'rows' => [],
            ];
        }

        $sql = <<<'SQL'
SELECT
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(c.title, '$.uk')),
        JSON_UNQUOTE(JSON_EXTRACT(c.title, '$.ru')),
        c.slug,
        'Без групи'
    ) AS group_name,
    ROUND(SUM(oi.total), 2) AS revenue_total
FROM bs_shop_order_items oi
JOIN bs_shop_orders o ON o.id = oi.shop_order_id
LEFT JOIN bs_products p ON p.id = oi.product_id
LEFT JOIN bs_product_categories c ON c.id = p.category_id
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY group_name
ORDER BY revenue_total DESC
SQL;

        $rows = DB::select($sql, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'brands' => $brands,
        ]);

        $normalized = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $normalized[] = [
                'group_name' => (string) ($item['group_name'] ?? 'Без групи'),
                'revenue_total' => max(0.0, (float) ($item['revenue_total'] ?? 0)),
            ];
        }

        $topRows = array_slice($normalized, 0, 6);
        $otherRows = array_slice($normalized, 6);
        $otherRevenue = 0.0;
        foreach ($otherRows as $row) {
            $otherRevenue += (float) ($row['revenue_total'] ?? 0);
        }
        if ($otherRevenue > 0) {
            $topRows[] = [
                'group_name' => 'Інші',
                'revenue_total' => round($otherRevenue, 2),
            ];
        }

        $total = 0.0;
        foreach ($topRows as $row) {
            $total += (float) ($row['revenue_total'] ?? 0);
        }

        $palette = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6', '#f79646', '#8db4e2'];
        $labels = [];
        $values = [];
        $colors = [];
        $legendRows = [];

        foreach ($topRows as $index => $row) {
            $name = (string) ($row['group_name'] ?? 'Без групи');
            $value = (float) ($row['revenue_total'] ?? 0);
            $percent = $total > 0 ? ($value * 100 / $total) : 0.0;

            $labels[] = $name;
            $values[] = $value;
            $colors[] = $palette[$index % count($palette)];

            $legendRows[] = [
                'group_name' => $name,
                'revenue_total' => round($value, 2),
                'percent' => round($percent, 2),
                'color' => $palette[$index % count($palette)],
            ];
        }

        return [
            'total' => round($total, 2),
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
            'rows' => $legendRows,
        ];
    }
}
