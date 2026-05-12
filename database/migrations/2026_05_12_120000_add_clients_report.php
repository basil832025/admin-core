<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $groupId = (int) DB::table('bs_report_groups')->where('slug', 'zagalne')->value('id');
        if ($groupId <= 0) {
            $groupId = (int) DB::table('bs_report_groups')->where('name', 'Загальне')->value('id');
        }

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата з',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'date_to',
                'type' => 'date',
                'label' => 'Дата по',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'date_field',
                'type' => 'select',
                'label' => 'Поле дати',
                'default' => 'registration',
                'required' => true,
                'options' => [
                    'registration' => 'Дата реєстрації',
                    'first_order' => 'Дата першого замовлення',
                    'last_order' => 'Дата останнього замовлення',
                ],
            ],
            [
                'key' => 'min_orders',
                'type' => 'number',
                'label' => 'Кількість замовлень (більше)',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'min_amount',
                'type' => 'number',
                'label' => 'Від суми',
                'default' => 0,
                'required' => false,
            ],
            [
                'key' => 'phone_filter',
                'type' => 'text',
                'label' => 'Телефон',
                'default' => null,
                'required' => false,
            ],
            [
                'key' => 'sort_by',
                'type' => 'select',
                'label' => 'Сортувати за',
                'default' => 'last_order_date',
                'required' => true,
                'options' => [
                    'client_id' => 'Client_ID',
                    'name' => 'Ім\'я',
                    'phone' => 'Телефон',
                    'email' => 'Email',
                    'registration_date' => 'Дата реєстрації',
                    'first_order_date' => 'Дата першого замовлення',
                    'last_order_date' => 'Дата останнього замовлення',
                    'orders_count' => 'Кількість замовлень',
                    'total_amount' => 'Загальна сума замовлень',
                    'average_check' => 'Середній чек',
                    'cancelled_orders_count' => 'Кількість скасувань',
                    'bonus_balance' => 'Бонусний баланс',
                    'used_bonuses' => 'Використовував бонуси',
                    'used_promotions' => 'Використовував акції',
                    'last_promotion' => 'Остання акція',
                    'favorite_category' => 'Улюблена категорія',
                    'favorite_pie_size' => 'Улюблений розмір пирога',
                    'city' => 'Місто',
                    'last_order_address' => 'Адреса останнього замовлення',
                    'client_comment' => 'Коментар клієнта',
                    'order_source' => 'Джерело замовлення',
                    'birthday' => 'Дата народження',
                ],
            ],
            [
                'key' => 'sort_direction',
                'type' => 'select',
                'label' => 'Напрям сортування',
                'default' => 'desc',
                'required' => true,
                'options' => [
                    'asc' => 'За зростанням',
                    'desc' => 'За спаданням',
                ],
            ],
        ];

        $dataSources = [
            [
                'key' => 'main',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
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
    favorite_size.favorite_pie_size AS favorite_pie_size,
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
        :phone_filter AS phone_filter,
        :sort_by AS sort_by,
        :sort_direction AS sort_direction
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
        ranked.client_id,
        SUBSTRING_INDEX(
            GROUP_CONCAT(ranked.size_value ORDER BY ranked.items_count DESC, ranked.size_value ASC SEPARATOR '||'),
            '||',
            1
        ) AS favorite_pie_size
    FROM (
        SELECT
            o.clients_id AS client_id,
            CASE
                WHEN TRIM(COALESCE(NULLIF(p.sku, ''), NULLIF(p.code2, ''), NULLIF(pp.sku, ''), NULLIF(pp.code2, ''))) REGEXP '^[0-9]{2,3}$'
                    THEN TRIM(COALESCE(NULLIF(p.sku, ''), NULLIF(p.code2, ''), NULLIF(pp.sku, ''), NULLIF(pp.code2, '')))
                ELSE NULL
            END AS size_value,
            SUM(COALESCE(oi.qty, 0)) AS items_count
        FROM bs_shop_orders o
        INNER JOIN bs_shop_order_items oi ON oi.shop_order_id = o.id
        LEFT JOIN bs_products p ON p.id = oi.product_id
        LEFT JOIN bs_products pp ON pp.id = p.parent_id
        LEFT JOIN bs_product_categories pc ON pc.id = COALESCE(p.category_id, pp.category_id)
        LEFT JOIN bs_product_categories parent_pc ON parent_pc.id = pc.parent_id
        WHERE o.deleted_at IS NULL
          AND o.status NOT IN ('cart', 'cancelled')
          AND o.clients_id IS NOT NULL
          AND parent_pc.slug = 'pies'
        GROUP BY
            o.clients_id,
            CASE
                WHEN TRIM(COALESCE(NULLIF(p.sku, ''), NULLIF(p.code2, ''), NULLIF(pp.sku, ''), NULLIF(pp.code2, ''))) REGEXP '^[0-9]{2,3}$'
                    THEN TRIM(COALESCE(NULLIF(p.sku, ''), NULLIF(p.code2, ''), NULLIF(pp.sku, ''), NULLIF(pp.code2, '')))
                ELSE NULL
            END
    ) AS ranked
    WHERE ranked.size_value IS NOT NULL
    GROUP BY ranked.client_id
) AS favorite_size ON favorite_size.client_id = c.id
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
ORDER BY
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'client_id' THEN c.id END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'client_id' THEN c.id END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'name' THEN c.name END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'name' THEN c.name END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'phone' THEN phone_clean.phone_digits END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'phone' THEN phone_clean.phone_digits END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'email' THEN c.email END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'email' THEN c.email END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'registration_date' THEN DATE(c.created_at) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'registration_date' THEN DATE(c.created_at) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'first_order_date' THEN stats.first_order_date END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'first_order_date' THEN stats.first_order_date END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_order_date' THEN stats.last_order_date END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_order_date' THEN stats.last_order_date END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'orders_count' THEN COALESCE(stats.orders_count, 0) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'orders_count' THEN COALESCE(stats.orders_count, 0) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'total_amount' THEN COALESCE(stats.total_amount, 0) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'total_amount' THEN COALESCE(stats.total_amount, 0) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'average_check' THEN COALESCE(stats.average_check, 0) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'average_check' THEN COALESCE(stats.average_check, 0) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'cancelled_orders_count' THEN COALESCE(stats.cancelled_orders_count, 0) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'cancelled_orders_count' THEN COALESCE(stats.cancelled_orders_count, 0) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'bonus_balance' THEN COALESCE(loyalty.balance, 0) END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'bonus_balance' THEN COALESCE(loyalty.balance, 0) END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'used_bonuses' THEN CASE WHEN COALESCE(bonus_usage.used_bonuses, 0) = 1 THEN 'Так' ELSE 'Ні' END END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'used_bonuses' THEN CASE WHEN COALESCE(bonus_usage.used_bonuses, 0) = 1 THEN 'Так' ELSE 'Ні' END END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'used_promotions' THEN CASE WHEN COALESCE(promo_usage.used_promotions, 0) = 1 THEN 'Так' ELSE 'Ні' END END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'used_promotions' THEN CASE WHEN COALESCE(promo_usage.used_promotions, 0) = 1 THEN 'Так' ELSE 'Ні' END END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_promotion' THEN promo_usage.last_promotion END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_promotion' THEN promo_usage.last_promotion END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'favorite_category' THEN favorite_category.favorite_category END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'favorite_category' THEN favorite_category.favorite_category END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'favorite_pie_size' THEN favorite_size.favorite_pie_size END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'favorite_pie_size' THEN favorite_size.favorite_pie_size END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'city' THEN last_order.city END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'city' THEN last_order.city END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_order_address' THEN last_order.last_address END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'last_order_address' THEN last_order.last_address END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'client_comment' THEN c.note END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'client_comment' THEN c.note END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'order_source' THEN last_order.order_source END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'order_source' THEN last_order.order_source END DESC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'asc' AND COALESCE(prm.sort_by, 'last_order_date') = 'birthday' THEN c.birthday END ASC,
    CASE WHEN COALESCE(prm.sort_direction, 'desc') = 'desc' AND COALESCE(prm.sort_by, 'last_order_date') = 'birthday' THEN c.birthday END DESC,
    COALESCE(stats.last_order_date, DATE(c.created_at)) DESC,
    c.id DESC
SQL,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Клієнти</div>
<div class="report-period">
    Поле дати: <strong>
        {% if params.date_field|default('registration') == 'first_order' %}
            Дата першого замовлення
        {% elseif params.date_field|default('registration') == 'last_order' %}
            Дата останнього замовлення
        {% else %}
            Дата реєстрації
        {% endif %}
    </strong>
    | Період: <strong>{{ params.date_from|default(null) ? params.date_from|date('d.m.Y') : 'усі дані' }}</strong> - <strong>{{ params.date_to|default(null) ? params.date_to|date('d.m.Y') : 'усі дані' }}</strong>
    | Кількість замовлень більше: <strong>{{ params.min_orders is defined and params.min_orders is not same as(null) and params.min_orders != '' ? params.min_orders : 'без фільтра' }}</strong>
    | Від суми: <strong>{{ params.min_amount|default(0) }}</strong>
    | Телефон: <strong>{{ params.phone_filter|default('') ?: 'без фільтра' }}</strong>
    | Сортування: <strong>{{ params.sort_by|default('last_order_date') }}</strong>
    | Напрям: <strong>{{ params.sort_direction|default('desc') == 'asc' ? 'За зростанням' : 'За спаданням' }}</strong>
</div>

<table class="report clients-report" style="margin-top: 10px;">
    <thead>
        <tr>
            <th>Client_ID</th>
            <th>Ім'я</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Дата<br>реєстрації</th>
            <th>Дата першого<br>замовлення</th>
            <th>Дата останнього<br>замовлення</th>
            <th class="num">Кількість<br>замовлень</th>
            <th class="num">Загальна сума<br>замовлень</th>
            <th class="num">Середній чек</th>
            <th class="num">Кількість<br>скасувань</th>
            <th class="num">Бонусний<br>баланс</th>
            <th>Використовував<br>бонуси</th>
            <th>Використовував<br>акції</th>
            <th>Остання<br>акція</th>
            <th>Улюблена<br>категорія</th>
            <th>Улюблений<br>розмір пирога</th>
            <th>Місто</th>
            <th>Адреса останнього<br>замовлення</th>
            <th>Коментар<br>клієнта</th>
            <th>Джерело<br>замовлення</th>
            <th>Дата<br>народження</th>
            <th>UTM<br>source</th>
            <th>UTM<br>campaign</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% set total_orders_count = 0 %}
            {% set total_amount_sum = 0 %}
            {% set total_cancelled_count = 0 %}
            {% set total_bonus_balance = 0 %}
            {% for row in datasets.main %}
                {% set total_orders_count = total_orders_count + (row.orders_count|default(0)) %}
                {% set total_amount_sum = total_amount_sum + (row.total_amount|default(0)) %}
                {% set total_cancelled_count = total_cancelled_count + (row.cancelled_orders_count|default(0)) %}
                {% set total_bonus_balance = total_bonus_balance + (row.bonus_balance|default(0)) %}
                <tr>
                    <td>{{ row.client_id|default('') }}</td>
                    <td>{{ row.name|default('') }}</td>
                    <td>{{ row.phone|default('') }}</td>
                    <td>{{ row.email|default('') }}</td>
                    <td>{{ row.registration_date|default(null) ? row.registration_date|date('d.m.Y') : '' }}</td>
                    <td>{{ row.first_order_date|default(null) ? row.first_order_date|date('d.m.Y') : '' }}</td>
                    <td>{{ row.last_order_date|default(null) ? row.last_order_date|date('d.m.Y') : '' }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ row.total_amount|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.average_check|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.cancelled_orders_count|default(0) }}</td>
                    <td class="num">{{ row.bonus_balance|default(0)|number_format(2, '.', ' ') }}</td>
                    <td>{{ row.used_bonuses|default('Ні') }}</td>
                    <td>{{ row.used_promotions|default('Ні') }}</td>
                    <td>{{ row.last_promotion|default('') }}</td>
                    <td>{{ row.favorite_category|default('') }}</td>
                    <td>{{ row.favorite_pie_size|default('') }}</td>
                    <td>{{ row.city|default('') }}</td>
                    <td>{{ row.last_order_address|default('') }}</td>
                    <td>{{ row.client_comment|default('') }}</td>
                    <td>{{ row.order_source|default('') }}</td>
                    <td>{{ row.birthday|default(null) ? row.birthday|date('d.m.Y') : '' }}</td>
                    <td>{{ row.utm_source|default('') }}</td>
                    <td>{{ row.utm_campaign|default('') }}</td>
                </tr>
            {% endfor %}
            <tr class="total-row">
                <td colspan="7">Усього</td>
                <td class="num">{{ total_orders_count }}</td>
                <td class="num">{{ total_amount_sum|number_format(2, '.', ' ') }}</td>
                <td class="num"></td>
                <td class="num">{{ total_cancelled_count }}</td>
                <td class="num">{{ total_bonus_balance|number_format(2, '.', ' ') }}</td>
                <td colspan="12"></td>
            </tr>
        {% else %}
            <tr>
                <td colspan="24">Дані відсутні.</td>
            </tr>
        {% endif %}
    </tbody>
</table>
TWIG;

        $customCss = <<<'CSS'
.report-title{font-size:16px;font-weight:700;margin-bottom:4px;color:#0f172a;}
.report-period{font-size:10px;line-height:1.35;color:#334155;}
table.clients-report{font-size:8.5px;line-height:1.2;table-layout:fixed;}
.clients-report th,.clients-report td{padding:4px 5px;word-break:break-word;}
.clients-report thead th{font-size:8px;white-space:normal;word-break:break-word;line-height:1.15;text-align:center;vertical-align:middle;hyphens:auto;}
.clients-report tbody td{text-align:center;vertical-align:middle;}
.clients-report tbody td.num,.clients-report .num{text-align:right;}
.clients-report .total-row td{font-weight:700;}
.clients-report td:nth-child(1){width:44px;}
.clients-report td:nth-child(2){width:90px;}
.clients-report td:nth-child(3){width:82px;}
.clients-report td:nth-child(4){width:110px;}
.clients-report td:nth-child(5),
.clients-report td:nth-child(6),
.clients-report td:nth-child(7),
.clients-report td:nth-child(22){white-space:nowrap;}
.clients-report td:nth-child(8),
.clients-report td:nth-child(9),
.clients-report td:nth-child(10),
.clients-report td:nth-child(11),
.clients-report td:nth-child(12){white-space:nowrap;}
.clients-report th:nth-child(23),.clients-report td:nth-child(23),
.clients-report th:nth-child(24),.clients-report td:nth-child(24){width:42px;}
CSS;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'clients_report'],
            [
                'name' => 'Клієнти',
                'type' => 'report',
                'report_group_id' => $groupId > 0 ? $groupId : null,
                'engine' => 'twig',
                'output_format' => 'pdf',
                'default_paper_preset' => 'custom',
                'default_paper_width_mm' => 297,
                'default_paper_height_mm' => 210,
                'default_margin_top_mm' => 6,
                'default_margin_right_mm' => 6,
                'default_margin_bottom_mm' => 6,
                'default_margin_left_mm' => 6,
                'editor_mode' => 'code',
                'css_preset' => 'report_table_dense',
                'custom_css' => $customCss,
                'description' => 'Клієнтський звіт з агрегатами по замовленнях, бонусах та акціях.',
                'template_body' => $templateBody,
                'parameters_schema' => json_encode($parametersSchema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('bs_print_templates')
            ->where('code', 'clients_report')
            ->delete();
    }
};
