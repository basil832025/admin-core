<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $groupId = $this->resolveRevenueGroupId($now);

        $reports = [
            $this->buildReport(
                code: 'sales_daily_summary',
                name: 'Продажі по днях',
                description: 'Динаміка продажів по днях: кількість замовлень, виторг та середній чек.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'main',
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
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Продажі по днях</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set sum_orders = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Дата</th>
            <th class="num">К-сть замовлень</th>
            <th class="num">Виторг</th>
            <th class="num">Середній чек</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set orders = row.orders_count|default(0) %}
                {% set revenue = row.revenue_total|default(0) %}
                {% set sum_orders = sum_orders + orders %}
                {% set sum_revenue = sum_revenue + revenue %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td class="num">{{ orders }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ sum_orders }}</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ (sum_orders > 0 ? (sum_revenue / sum_orders) : 0)|number_format(2, '.', ' ') }}</td>
        </tr>
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_by_source',
                name: 'Продажі по сайтах (джерелах)',
                description: 'Розподіл виторгу та замовлень по сайтах/джерелах.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'main',
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
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY source_key, source_name
ORDER BY revenue_total DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Продажі по сайтах (джерелах)</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set sum_orders = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Сайт / джерело</th>
            <th class="num">К-сть замовлень</th>
            <th class="num">Виторг</th>
            <th class="num">Середній чек</th>
            <th class="num">Частка виторгу, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set sum_orders = sum_orders + (row.orders_count|default(0)) %}
                {% set sum_revenue = sum_revenue + (row.revenue_total|default(0)) %}
            {% endfor %}

            {% for row in datasets.main %}
                {% set revenue = row.revenue_total|default(0) %}
                <tr>
                    <td>{{ row.source_name|default('Невизначено') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (sum_revenue > 0 ? (revenue * 100 / sum_revenue) : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="5">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ sum_orders }}</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ (sum_orders > 0 ? (sum_revenue / sum_orders) : 0)|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_top_products',
                name: 'Топ товарів по виторгу',
                description: 'Рейтинг товарів за виторгом та кількістю.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(withTopLimit: true),
                dataSources: [
                    [
                        'key' => 'main',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    oi.product_id,
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.uk')),
        JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.ru')),
        p.slug,
        CONCAT('Товар #', oi.product_id)
    ) AS product_name,
    ROUND(SUM(oi.qty), 2) AS qty_total,
    ROUND(SUM(oi.total), 2) AS revenue_total,
    ROUND(SUM(oi.total) / NULLIF(SUM(oi.qty), 0), 2) AS avg_unit_price
FROM bs_shop_order_items oi
JOIN bs_shop_orders o ON o.id = oi.shop_order_id
LEFT JOIN bs_products p ON p.id = oi.product_id
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY oi.product_id, product_name
ORDER BY revenue_total DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Топ товарів по виторгу</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set limit = params.top_limit|default(20) %}
{% set sum_qty = 0 %}
{% set sum_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>#</th>
            <th>Товар</th>
            <th class="num">К-сть</th>
            <th class="num">Сер. ціна</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% set rows = datasets.main|slice(0, limit) %}
            {% for row in rows %}
                {% set sum_qty = sum_qty + (row.qty_total|default(0)) %}
                {% set sum_revenue = sum_revenue + (row.revenue_total|default(0)) %}
            {% endfor %}
            {% for row in rows %}
                {% set revenue = row.revenue_total|default(0) %}
                <tr>
                    <td>{{ loop.index }}</td>
                    <td>{{ row.product_name|default('Товар') }}</td>
                    <td class="num">{{ row.qty_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_unit_price|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (sum_revenue > 0 ? (revenue * 100 / sum_revenue) : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="6">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td colspan="2">Разом (Top {{ limit }})</td>
            <td class="num">{{ sum_qty|number_format(2, '.', ' ') }}</td>
            <td class="num">&nbsp;</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_abc_products',
                name: 'ABC-аналіз товарів',
                description: 'Класифікація товарів A/B/C за кумулятивною часткою виторгу.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(withTopLimit: false),
                dataSources: [
                    [
                        'key' => 'main',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    oi.product_id,
    COALESCE(
        JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.uk')),
        JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.ru')),
        p.slug,
        CONCAT('Товар #', oi.product_id)
    ) AS product_name,
    ROUND(SUM(oi.qty), 2) AS qty_total,
    ROUND(SUM(oi.total), 2) AS revenue_total
FROM bs_shop_order_items oi
JOIN bs_shop_orders o ON o.id = oi.shop_order_id
LEFT JOIN bs_products p ON p.id = oi.product_id
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY oi.product_id, product_name
ORDER BY revenue_total DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: ABC-аналіз товарів</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set total_revenue = 0 %}
{% if datasets.main is defined and datasets.main|length %}
    {% for row in datasets.main %}
        {% set total_revenue = total_revenue + (row.revenue_total|default(0)) %}
    {% endfor %}
{% endif %}
{% set cumulative = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>#</th>
            <th>Товар</th>
            <th class="num">К-сть</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
            <th class="num">Кумулятивно, %</th>
            <th class="num">Клас</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set revenue = row.revenue_total|default(0) %}
                {% set share = total_revenue > 0 ? (revenue * 100 / total_revenue) : 0 %}
                {% set cumulative = cumulative + share %}
                {% set abc = cumulative <= 80 ? 'A' : (cumulative <= 95 ? 'B' : 'C') %}
                <tr>
                    <td>{{ loop.index }}</td>
                    <td>{{ row.product_name|default('Товар') }}</td>
                    <td class="num">{{ row.qty_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ share|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ cumulative|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ abc }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="7">Дані за період відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_discounts_effect',
                name: 'Знижки та їх вплив',
                description: 'Сума знижок, частка замовлень зі знижкою та динаміка по днях.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'main',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    DATE(COALESCE(o.date_order, o.created_at)) AS report_date,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(SUM(ABS(COALESCE(o.discount_total, 0)) + ABS(COALESCE(o.sale_sum, 0))), 2) AS discount_total,
    SUM(CASE WHEN (ABS(COALESCE(o.discount_total, 0)) + ABS(COALESCE(o.sale_sum, 0))) > 0 THEN 1 ELSE 0 END) AS discounted_orders
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
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Знижки та їх вплив</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set sum_orders = 0 %}
{% set sum_discounted = 0 %}
{% set sum_revenue = 0 %}
{% set sum_discount = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Дата</th>
            <th class="num">Замовлень</th>
            <th class="num">Зі знижкою</th>
            <th class="num">Знижка, грн</th>
            <th class="num">Виторг, грн</th>
            <th class="num">Частка зі знижкою, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set orders = row.orders_count|default(0) %}
                {% set discounted = row.discounted_orders|default(0) %}
                {% set discount = row.discount_total|default(0) %}
                {% set revenue = row.revenue_total|default(0) %}
                {% set sum_orders = sum_orders + orders %}
                {% set sum_discounted = sum_discounted + discounted %}
                {% set sum_discount = sum_discount + discount %}
                {% set sum_revenue = sum_revenue + revenue %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td class="num">{{ orders }}</td>
                    <td class="num">{{ discounted }}</td>
                    <td class="num">{{ discount|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (orders > 0 ? discounted * 100 / orders : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="6">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ sum_orders }}</td>
            <td class="num">{{ sum_discounted }}</td>
            <td class="num">{{ sum_discount|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ (sum_orders > 0 ? sum_discounted * 100 / sum_orders : 0)|number_format(2, '.', ' ') }}</td>
        </tr>
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_delivery_vs_pickup',
                name: 'Доставка vs Самовивіз',
                description: 'Порівняння каналів виконання: доставка проти самовивозу.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'main',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    CASE WHEN o.self_pickup = 1 THEN 'Самовивіз' ELSE 'Доставка' END AS delivery_channel,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total,
    ROUND(SUM(COALESCE(o.shipping_total, o.shipping_price, 0)), 2) AS shipping_total,
    ROUND(AVG(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS avg_check
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status NOT IN ('cart', 'cancelled')
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY delivery_channel
ORDER BY revenue_total DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Доставка vs Самовивіз</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set sum_orders = 0 %}
{% set sum_revenue = 0 %}
{% set sum_shipping = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Канал</th>
            <th class="num">К-сть замовлень</th>
            <th class="num">Виторг</th>
            <th class="num">Доставка</th>
            <th class="num">Середній чек</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set sum_orders = sum_orders + (row.orders_count|default(0)) %}
                {% set sum_revenue = sum_revenue + (row.revenue_total|default(0)) %}
                {% set sum_shipping = sum_shipping + (row.shipping_total|default(0)) %}
            {% endfor %}

            {% for row in datasets.main %}
                {% set revenue = row.revenue_total|default(0) %}
                <tr>
                    <td>{{ row.delivery_channel|default('-') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.shipping_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (sum_revenue > 0 ? revenue * 100 / sum_revenue : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="6">Дані за період відсутні.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ sum_orders }}</td>
            <td class="num">{{ sum_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ sum_shipping|number_format(2, '.', ' ') }}</td>
            <td class="num">{{ (sum_orders > 0 ? sum_revenue / sum_orders : 0)|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_status_funnel',
                name: 'Воронка статусів замовлень',
                description: 'Розподіл замовлень по статусах за період із показником конверсії.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'main',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    o.status,
    COUNT(*) AS orders_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS revenue_total
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status <> 'cart'
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY o.status
ORDER BY orders_count DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Воронка статусів замовлень</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>
{% set total_orders = 0 %}
{% set delivered_orders = 0 %}
{% set cancelled_orders = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Статус</th>
            <th class="num">К-сть</th>
            <th class="num">Виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                {% set total_orders = total_orders + count %}
                {% if row.status|default('') == 'delivered' %}
                    {% set delivered_orders = delivered_orders + count %}
                {% endif %}
                {% if row.status|default('') == 'cancelled' %}
                    {% set cancelled_orders = cancelled_orders + count %}
                {% endif %}
            {% endfor %}
            {% for row in datasets.main %}
                {% set count = row.orders_count|default(0) %}
                <tr>
                    <td>{{ row.status|default('-') }}</td>
                    <td class="num">{{ count }}</td>
                    <td class="num">{{ row.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (total_orders > 0 ? count * 100 / total_orders : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані за період відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>

<div style="margin-top:8px;font-size:12px;">
    <strong>Конверсія у delivered:</strong> {{ (total_orders > 0 ? delivered_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}% &nbsp;|&nbsp;
    <strong>Доля cancelled:</strong> {{ (total_orders > 0 ? cancelled_orders * 100 / total_orders : 0)|number_format(2, '.', ' ') }}%
</div>
TWIG,
            ),

            $this->buildReport(
                code: 'sales_cancellations_analysis',
                name: 'Скасування: причини та джерела',
                description: 'Аналіз скасованих замовлень за причинами та сайтами.',
                reportGroupId: $groupId,
                parametersSchema: $this->commonSalesParams(),
                dataSources: [
                    [
                        'key' => 'reasons',
                        'type' => 'sql',
                        'connection' => null,
                        'enabled' => true,
                        'query' => <<<'SQL'
SELECT
    COALESCE(NULLIF(TRIM(o.extra_reason), ''), 'Без причини') AS cancel_reason,
    COUNT(*) AS cancelled_count,
    ROUND(SUM(COALESCE(o.grand_total, o.total_price_sale, o.total_price, 0)), 2) AS lost_revenue
FROM bs_shop_orders o
WHERE o.deleted_at IS NULL
  AND o.status = 'cancelled'
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY cancel_reason
ORDER BY cancelled_count DESC
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
    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
    COUNT(*) AS total_count,
    ROUND(
        SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0),
        2
    ) AS cancel_rate
FROM bs_shop_orders o
LEFT JOIN bs_cc_sources s ON s.id = o.source_id
WHERE o.deleted_at IS NULL
  AND o.status <> 'cart'
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN :date_from AND :date_to
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY source_key, source_name
ORDER BY cancel_rate DESC, cancelled_count DESC
SQL,
                    ],
                ],
                templateBody: <<<'TWIG'
<div class="report-title">Звіт: Скасування (причини та джерела)</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

<h4 style="margin:10px 0 6px;">1) Причини скасувань</h4>
{% set total_cancelled = 0 %}
{% set total_lost_revenue = 0 %}
<table class="report">
    <thead>
        <tr>
            <th>Причина</th>
            <th class="num">К-сть</th>
            <th class="num">Втрачений виторг</th>
            <th class="num">Частка, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.reasons is defined and datasets.reasons|length %}
            {% for row in datasets.reasons %}
                {% set total_cancelled = total_cancelled + (row.cancelled_count|default(0)) %}
                {% set total_lost_revenue = total_lost_revenue + (row.lost_revenue|default(0)) %}
            {% endfor %}
            {% for row in datasets.reasons %}
                {% set count = row.cancelled_count|default(0) %}
                <tr>
                    <td>{{ row.cancel_reason|default('Без причини') }}</td>
                    <td class="num">{{ count }}</td>
                    <td class="num">{{ row.lost_revenue|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (total_cancelled > 0 ? count * 100 / total_cancelled : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Скасувань за період не знайдено.</td></tr>
        {% endif %}
        <tr class="total-row">
            <td>Разом</td>
            <td class="num">{{ total_cancelled }}</td>
            <td class="num">{{ total_lost_revenue|number_format(2, '.', ' ') }}</td>
            <td class="num">100.00</td>
        </tr>
    </tbody>
</table>

<h4 style="margin:14px 0 6px;">2) Рівень скасувань по сайтах</h4>
<table class="report">
    <thead>
        <tr>
            <th>Сайт / джерело</th>
            <th class="num">Скасовано</th>
            <th class="num">Всього замовлень</th>
            <th class="num">Рівень скасувань, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.by_source is defined and datasets.by_source|length %}
            {% for row in datasets.by_source %}
                <tr>
                    <td>{{ row.source_name|default('Невизначено') }}</td>
                    <td class="num">{{ row.cancelled_count|default(0) }}</td>
                    <td class="num">{{ row.total_count|default(0) }}</td>
                    <td class="num">{{ row.cancel_rate|default(0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані по джерелах відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>
TWIG,
            ),
        ];

        foreach ($reports as $report) {
            DB::table('bs_print_templates')->updateOrInsert(
                ['code' => $report['code']],
                $report,
            );
        }
    }

    public function down(): void
    {
        DB::table('bs_print_templates')
            ->whereIn('code', [
                'sales_daily_summary',
                'sales_by_source',
                'sales_top_products',
                'sales_abc_products',
                'sales_discounts_effect',
                'sales_delivery_vs_pickup',
                'sales_status_funnel',
                'sales_cancellations_analysis',
            ])
            ->delete();
    }

    private function resolveRevenueGroupId(\Illuminate\Support\Carbon $now): int
    {
        $groupId = (int) DB::table('bs_report_groups')->where('slug', 'vitorgi')->value('id');
        if ($groupId > 0) {
            return $groupId;
        }

        $groupId = (int) DB::table('bs_report_groups')->where('name', 'Виторги')->value('id');
        if ($groupId > 0) {
            return $groupId;
        }

        DB::table('bs_report_groups')->insert([
            'name' => 'Виторги',
            'slug' => 'vitorgi',
            'sort' => 20,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('bs_report_groups')->where('slug', 'vitorgi')->value('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function commonSalesParams(bool $withTopLimit = false): array
    {
        $params = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата з',
                'default' => null,
                'required' => true,
            ],
            [
                'key' => 'date_to',
                'type' => 'date',
                'label' => 'Дата по',
                'default' => null,
                'required' => true,
            ],
            [
                'key' => 'brands',
                'type' => 'dictionary',
                'label' => 'Сайт / джерело',
                'default' => 'all',
                'required' => false,
                'dictionary_query' => $this->brandsDictionaryQuery(),
                'dictionary_connection' => null,
                'dictionary_searchable' => true,
            ],
        ];

        if ($withTopLimit) {
            $params[] = [
                'key' => 'top_limit',
                'type' => 'number',
                'label' => 'Ліміт позицій у топі',
                'default' => 20,
                'required' => true,
            ];
        }

        return $params;
    }

    private function brandsDictionaryQuery(): string
    {
        return <<<'SQL'
SELECT 'all' AS value, 'Всі сайти' AS label
UNION ALL
SELECT CAST(id AS CHAR) AS value, name AS label
FROM bs_cc_sources
WHERE is_active = 1
UNION ALL
SELECT 'local' AS value, '3 Пирога (local)' AS label
ORDER BY label
SQL;
    }

    /**
     * @param array<int, array<string, mixed>> $parametersSchema
     * @param array<int, array<string, mixed>> $dataSources
     * @return array<string, mixed>
     */
    private function buildReport(
        string $code,
        string $name,
        string $description,
        int $reportGroupId,
        array $parametersSchema,
        array $dataSources,
        string $templateBody,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'type' => 'report',
            'report_group_id' => $reportGroupId,
            'engine' => 'twig',
            'output_format' => 'pdf',
            'default_paper_preset' => 'a4',
            'default_margin_top_mm' => 8,
            'default_margin_right_mm' => 8,
            'default_margin_bottom_mm' => 8,
            'default_margin_left_mm' => 8,
            'editor_mode' => 'code',
            'css_preset' => 'report_table_default',
            'description' => $description,
            'template_body' => $templateBody,
            'parameters_schema' => json_encode($parametersSchema, JSON_UNESCAPED_UNICODE),
            'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }
};
