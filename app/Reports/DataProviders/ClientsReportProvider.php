<?php

namespace App\Reports\DataProviders;

use App\Models\Shop\ProductCategory;
use App\Models\Shop\ProductCharacteristicValue;
use Illuminate\Support\Facades\DB;

class ClientsReportProvider implements ReportDataProviderInterface
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function resolve(array $params, array $context = []): array
    {
        $rows = $this->fetchBaseRows($params);

        if ($rows === []) {
            return [];
        }

        $clientIds = [];
        foreach ($rows as $row) {
            $clientIds[] = (int) ($row['client_id'] ?? 0);
        }

        $favoriteSizes = $this->resolveFavoritePieSizes(array_values(array_unique(array_filter($clientIds))));

        foreach ($rows as &$row) {
            $clientId = (int) ($row['client_id'] ?? 0);
            $row['favorite_pie_size'] = $favoriteSizes[$clientId] ?? '';
        }
        unset($row);

        return $this->sortRows($rows, $params);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchBaseRows(array $params): array
    {
        $sql = <<<'SQL'
SELECT
    c.id AS client_id,
    c.name AS name,
    CASE
        WHEN LENGTH(phone_clean.phone_digits) >= 10 THEN RIGHT(phone_clean.phone_digits, 10)
        ELSE phone_clean.phone_digits
    END AS phone,
    c.email AS email,
    DATE(c.created_at) AS registration_date,
    stats.first_order_date AS first_order_date,
    stats.last_order_date AS last_order_date,
    COALESCE(stats.orders_count, 0) AS orders_count,
    ROUND(COALESCE(stats.total_amount, 0), 2) AS total_amount,
    ROUND(COALESCE(stats.average_check, 0), 2) AS average_check,
    COALESCE(stats.cancelled_orders_count, 0) AS cancelled_orders_count,
    ROUND(COALESCE(loyalty.balance, 0), 2) AS bonus_balance,
    CASE WHEN COALESCE(bonus_usage.used_bonuses, 0) = 1 THEN 'Так' ELSE 'Ні' END AS used_bonuses,
    CASE WHEN COALESCE(promo_usage.used_promotions, 0) = 1 THEN 'Так' ELSE 'Ні' END AS used_promotions,
    promo_usage.last_promotion AS last_promotion,
    favorite_category.favorite_category AS favorite_category,
    '' AS favorite_pie_size,
    last_order.city AS city,
    last_order.last_address AS last_order_address,
    c.note AS client_comment,
    last_order.order_source AS order_source,
    c.birthday AS birthday,
    '' AS utm_source,
    '' AS utm_campaign
FROM bs_clients c
CROSS JOIN (
    SELECT
        :date_from AS date_from,
        :date_to AS date_to,
        :date_field AS date_field,
        :min_orders AS min_orders,
        :min_amount AS min_amount,
        :phone_filter AS phone_filter
) AS prm
LEFT JOIN (
    SELECT
        c2.id AS client_id,
        REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(c2.phone, '')), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), ',', ''), '/', '') AS phone_digits
    FROM bs_clients c2
) AS phone_clean ON phone_clean.client_id = c.id
LEFT JOIN (
    SELECT
        o.clients_id AS client_id,
        MIN(DATE(COALESCE(o.date_order, o.created_at))) AS first_order_date,
        MAX(DATE(COALESCE(o.date_order, o.created_at))) AS last_order_date,
        COUNT(*) AS orders_count,
        ROUND(SUM(CASE
            WHEN o.status <> 'cancelled' THEN COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)
            ELSE 0
        END), 2) AS total_amount,
        ROUND(AVG(CASE
            WHEN o.status <> 'cancelled' THEN COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)
            ELSE NULL
        END), 2) AS average_check,
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders_count
    FROM bs_shop_orders o
    WHERE o.deleted_at IS NULL
      AND o.status <> 'cart'
      AND o.clients_id IS NOT NULL
    GROUP BY o.clients_id
) AS stats ON stats.client_id = c.id
LEFT JOIN (
    SELECT
        la.client_id,
        la.balance
    FROM bs_loyalty_accounts la
    WHERE la.client_id IS NOT NULL
) AS loyalty ON loyalty.client_id = c.id
LEFT JOIN (
    SELECT
        la.client_id,
        1 AS used_bonuses
    FROM bs_loyalty_accounts la
    INNER JOIN bs_loyalty_transactions lt ON lt.account_id = la.id
    WHERE la.client_id IS NOT NULL
      AND lt.type = 'spend'
    GROUP BY la.client_id
) AS bonus_usage ON bonus_usage.client_id = c.id
LEFT JOIN (
    SELECT
        p.client_id,
        1 AS used_promotions,
        SUBSTRING_INDEX(
            GROUP_CONCAT(NULLIF(TRIM(COALESCE(p.label, '')), '') ORDER BY p.order_sort_date DESC, p.order_id DESC, p.adjustment_id DESC SEPARATOR '||'),
            '||',
            1
        ) AS last_promotion
    FROM (
        SELECT
            o.clients_id AS client_id,
            o.id AS order_id,
            a.id AS adjustment_id,
            COALESCE(o.date_order, DATE(o.created_at)) AS order_sort_date,
            a.label AS label
        FROM bs_shop_orders o
        INNER JOIN bs_shop_order_adjustments a ON a.shop_order_id = o.id
        WHERE o.deleted_at IS NULL
          AND o.status <> 'cart'
          AND o.clients_id IS NOT NULL
          AND (
              a.promotion_id IS NOT NULL
              OR a.promo_code_id IS NOT NULL
              OR a.type IN ('promotion', 'coupon')
          )
    ) AS p
    GROUP BY p.client_id
) AS promo_usage ON promo_usage.client_id = c.id
LEFT JOIN (
    SELECT
        ranked.client_id,
        SUBSTRING_INDEX(
            GROUP_CONCAT(ranked.category_name ORDER BY ranked.items_count DESC, ranked.category_name ASC SEPARATOR '||'),
            '||',
            1
        ) AS favorite_category
    FROM (
        SELECT
            o.clients_id AS client_id,
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.uk')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.ua')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.ru')), ''),
                pc.slug
            ) AS category_name,
            SUM(COALESCE(oi.qty, 0)) AS items_count
        FROM bs_shop_orders o
        INNER JOIN bs_shop_order_items oi ON oi.shop_order_id = o.id
        LEFT JOIN bs_products p ON p.id = oi.product_id
        LEFT JOIN bs_products pp ON pp.id = p.parent_id
        LEFT JOIN bs_product_categories pc ON pc.id = COALESCE(p.category_id, pp.category_id)
        WHERE o.deleted_at IS NULL
          AND o.status NOT IN ('cart', 'cancelled')
          AND o.clients_id IS NOT NULL
          AND pc.id IS NOT NULL
        GROUP BY
            o.clients_id,
            COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.uk')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.ua')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(pc.title, '$.ru')), ''),
                pc.slug
            )
    ) AS ranked
    GROUP BY ranked.client_id
) AS favorite_category ON favorite_category.client_id = c.id
LEFT JOIN (
    SELECT
        lo.client_id,
        lo.city,
        lo.last_address,
        lo.order_source
    FROM (
        SELECT
            o.clients_id AS client_id,
            o.id AS order_id,
            COALESCE(o.date_order, DATE(o.created_at)) AS order_sort_date,
            CONCAT(DATE_FORMAT(COALESCE(o.date_order, o.created_at), '%Y%m%d'), '-', LPAD(o.id, 10, '0')) AS sort_key,
            NULLIF(TRIM(ca.city), '') AS city,
            COALESCE(
                NULLIF(TRIM(ca.formatted_address), ''),
                NULLIF(TRIM(CONCAT_WS(', ',
                    NULLIF(TRIM(ca.city), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(TRIM(ca.street), ''), NULLIF(TRIM(ca.house), ''))), ''),
                    NULLIF(TRIM(CONCAT('кв. ', ca.apartment)), 'кв.')
                )), '')
            ) AS last_address,
            COALESCE(NULLIF(TRIM(s.name), ''), '3 Пироги') AS order_source
        FROM bs_shop_orders o
        LEFT JOIN bs_client_addresses ca ON ca.id = o.client_address_id
        LEFT JOIN bs_cc_sources s ON s.id = o.source_id
        WHERE o.deleted_at IS NULL
          AND o.status <> 'cart'
          AND o.clients_id IS NOT NULL
    ) AS lo
    INNER JOIN (
        SELECT
            picked.client_id,
            MAX(picked.sort_key) AS max_sort_key
        FROM (
            SELECT
                o.clients_id AS client_id,
                CONCAT(DATE_FORMAT(COALESCE(o.date_order, o.created_at), '%Y%m%d'), '-', LPAD(o.id, 10, '0')) AS sort_key
            FROM bs_shop_orders o
            WHERE o.deleted_at IS NULL
              AND o.status <> 'cart'
              AND o.clients_id IS NOT NULL
        ) AS picked
        GROUP BY picked.client_id
    ) AS latest_order ON latest_order.client_id = lo.client_id
        AND latest_order.max_sort_key = lo.sort_key
) AS last_order ON last_order.client_id = c.id
WHERE (
        COALESCE(NULLIF(prm.date_field, ''), 'registration') <> 'registration'
        OR (
            (prm.date_from IS NULL OR DATE(c.created_at) >= prm.date_from)
            AND (prm.date_to IS NULL OR DATE(c.created_at) <= prm.date_to)
        )
    )
  AND (
        COALESCE(NULLIF(prm.date_field, ''), 'registration') <> 'first_order'
        OR (
            (prm.date_from IS NULL OR stats.first_order_date >= prm.date_from)
            AND (prm.date_to IS NULL OR stats.first_order_date <= prm.date_to)
        )
    )
  AND (
        COALESCE(NULLIF(prm.date_field, ''), 'registration') <> 'last_order'
        OR (
            (prm.date_from IS NULL OR stats.last_order_date >= prm.date_from)
            AND (prm.date_to IS NULL OR stats.last_order_date <= prm.date_to)
        )
    )
  AND (
        prm.min_orders IS NULL
        OR prm.min_orders = ''
        OR COALESCE(stats.orders_count, 0) > prm.min_orders
    )
  AND (
        COALESCE(prm.min_amount, 0) <= 0
        OR COALESCE(stats.total_amount, 0) >= prm.min_amount
    )
  AND (
        prm.phone_filter IS NULL
        OR prm.phone_filter = ''
        OR phone_clean.phone_digits LIKE CONCAT('%', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(prm.phone_filter), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), ',', ''), '/', ''), '%')
    )
ORDER BY COALESCE(stats.last_order_date, DATE(c.created_at)) DESC, c.id DESC
SQL;

        $bindings = [
            'date_from' => $this->nullableString($params['date_from'] ?? null),
            'date_to' => $this->nullableString($params['date_to'] ?? null),
            'date_field' => $this->nullableString($params['date_field'] ?? 'registration') ?? 'registration',
            'min_orders' => $this->nullableString($params['min_orders'] ?? null),
            'min_amount' => $this->normalizeNumber($params['min_amount'] ?? 0),
            'phone_filter' => $this->nullableString($params['phone_filter'] ?? null),
        ];

        return array_map(static fn ($row): array => (array) $row, DB::select($sql, $bindings));
    }

    /**
     * @param array<int, int> $clientIds
     * @return array<int, string>
     */
    private function resolveFavoritePieSizes(array $clientIds): array
    {
        if ($clientIds === []) {
            return [];
        }

        $piesCategory = ProductCategory::query()->where('slug', 'pies')->first(['id']);
        if (! $piesCategory) {
            return [];
        }

        $categoryIds = array_values(array_unique(array_merge([
            (int) $piesCategory->id,
        ], $piesCategory->getDescendantIds())));

        $rows = DB::table('bs_shop_order_items as oi')
            ->join('bs_shop_orders as o', 'o.id', '=', 'oi.shop_order_id')
            ->leftJoin('bs_products as p', 'p.id', '=', 'oi.product_id')
            ->leftJoin('bs_products as pp', 'pp.id', '=', 'p.parent_id')
            ->whereNull('o.deleted_at')
            ->whereNotIn('o.status', ['cart', 'cancelled'])
            ->whereIn('o.clients_id', $clientIds)
            ->whereIn(DB::raw('COALESCE(p.category_id, pp.category_id)'), $categoryIds)
            ->orderBy('o.clients_id')
            ->get([
                'o.clients_id as client_id',
                'oi.product_id',
                'oi.qty',
                'p.parent_id as parent_product_id',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $productIds = [];
        foreach ($rows as $row) {
            $productIds[] = (int) ($row->product_id ?? 0);
            $parentId = (int) ($row->parent_product_id ?? 0);
            if ($parentId > 0) {
                $productIds[] = $parentId;
            }
        }
        $productIds = array_values(array_unique(array_filter($productIds)));

        $characteristicRows = ProductCharacteristicValue::query()
            ->with([
                'characteristic:id,slug',
                'characteristicValue:id,characteristic_id,value',
                'characteristicValue.characteristic:id,slug',
            ])
            ->whereIn('product_id', $productIds)
            ->get();

        $rowsByProduct = [];
        foreach ($characteristicRows as $characteristicRow) {
            $rowsByProduct[(int) $characteristicRow->product_id][] = $characteristicRow;
        }

        $sizeCounters = [];
        foreach ($rows as $row) {
            $clientId = (int) ($row->client_id ?? 0);
            $productId = (int) ($row->product_id ?? 0);
            $parentProductId = (int) ($row->parent_product_id ?? 0);
            $size = $this->resolveUnitLabel($productId, $parentProductId, $rowsByProduct);
            if ($size === '') {
                continue;
            }

            $qty = (float) ($row->qty ?? 0);
            if ($qty <= 0) {
                $qty = 1;
            }

            if (! isset($sizeCounters[$clientId])) {
                $sizeCounters[$clientId] = [];
            }

            if (! isset($sizeCounters[$clientId][$size])) {
                $sizeCounters[$clientId][$size] = 0.0;
            }

            $sizeCounters[$clientId][$size] += $qty;
        }

        $result = [];
        foreach ($sizeCounters as $clientId => $sizes) {
            uksort($sizes, static fn (string $a, string $b): int => strcmp($a, $b));
            arsort($sizes, SORT_NUMERIC);
            $result[(int) $clientId] = (string) array_key_first($sizes);
        }

        return $result;
    }

    /**
     * @param array<int, array<int, ProductCharacteristicValue>> $rowsByProduct
     */
    private function resolveUnitLabel(int $productId, int $parentProductId, array $rowsByProduct): string
    {
        $priority = ['rozmir-pirogiv', 'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];
        $productIds = array_values(array_unique(array_filter([$productId, $parentProductId])));

        foreach ($productIds as $id) {
            $productRows = $rowsByProduct[$id] ?? [];
            foreach ($priority as $slug) {
                foreach ($productRows as $row) {
                    $rowSlug = $row->characteristic?->slug ?? $row->characteristicValue?->characteristic?->slug;
                    if ($rowSlug !== $slug) {
                        continue;
                    }

                    $value = $this->resolveUnitValueFromRow($row);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }

        return '';
    }

    private function resolveUnitValueFromRow(ProductCharacteristicValue $row): string
    {
        $value = trim((string) ($row->value_text ?? ''));
        if ($value !== '') {
            return $value;
        }

        if ($row->value_number !== null) {
            return (string) $row->value_number;
        }

        if (! $row->characteristicValue) {
            return '';
        }

        $label = trim((string) ($row->characteristicValue->label ?? ''));
        if ($label !== '') {
            return $label;
        }

        $raw = $row->characteristicValue->getRawOriginal('value');
        if (! is_string($raw) || trim($raw) === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $locale = app()->getLocale();

            return trim((string) (
                $decoded[$locale]
                ?? $decoded['uk']
                ?? $decoded['ru']
                ?? $decoded['en']
                ?? (count($decoded) ? reset($decoded) : '')
            ));
        }

        return trim($raw, " \t\n\r\0\x0B\"");
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, array $params): array
    {
        $sortBy = (string) ($params['sort_by'] ?? 'last_order_date');
        $direction = mb_strtolower((string) ($params['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowed = [
            'client_id', 'name', 'phone', 'email', 'registration_date', 'first_order_date', 'last_order_date',
            'orders_count', 'total_amount', 'average_check', 'cancelled_orders_count', 'bonus_balance',
            'used_bonuses', 'used_promotions', 'last_promotion', 'favorite_category', 'favorite_pie_size',
            'city', 'last_order_address', 'client_comment', 'order_source', 'birthday',
        ];

        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = 'last_order_date';
        }

        usort($rows, function (array $left, array $right) use ($sortBy, $direction): int {
            $leftValue = $this->sortValue($left[$sortBy] ?? null, $sortBy);
            $rightValue = $this->sortValue($right[$sortBy] ?? null, $sortBy);

            if ($leftValue === $rightValue) {
                $fallback = strcmp((string) ($right['last_order_date'] ?? ''), (string) ($left['last_order_date'] ?? ''));
                if ($fallback !== 0) {
                    return $fallback;
                }

                return ((int) ($right['client_id'] ?? 0)) <=> ((int) ($left['client_id'] ?? 0));
            }

            $comparison = $leftValue <=> $rightValue;

            return $direction === 'asc' ? $comparison : -$comparison;
        });

        return $rows;
    }

    private function sortValue(mixed $value, string $sortBy): int|float|string
    {
        if (in_array($sortBy, ['client_id', 'orders_count', 'cancelled_orders_count'], true)) {
            return (int) $value;
        }

        if (in_array($sortBy, ['total_amount', 'average_check', 'bonus_balance'], true)) {
            return (float) $value;
        }

        return mb_strtolower(trim((string) $value));
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeNumber(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
