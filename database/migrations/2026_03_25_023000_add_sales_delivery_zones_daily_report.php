<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $groupId = (int) DB::table('bs_report_groups')->where('slug', 'vitorgi')->value('id');
        if ($groupId <= 0) {
            $groupId = (int) DB::table('bs_report_groups')->where('name', 'Виторги')->value('id');
        }

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата с',
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
                'label' => 'Сайт / источник',
                'default' => 'all',
                'required' => false,
                'dictionary_query' => <<<'SQL'
SELECT 'all' AS value, 'Все сайты' AS label
UNION ALL
SELECT CAST(id AS CHAR) AS value, name AS label
FROM bs_cc_sources
WHERE is_active = 1
UNION ALL
SELECT 'local' AS value, '3 Пирога (local)' AS label
ORDER BY label
SQL,
                'dictionary_connection' => null,
                'dictionary_searchable' => true,
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
    DATE(q.delivered_at) AS report_date,
    q.zone_name,
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
GROUP BY DATE(q.delivered_at), q.zone_name
ORDER BY report_date ASC, deliveries_count DESC, q.zone_name ASC
SQL,
            ],
            [
                'key' => 'chart',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
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
SQL,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Доставки по зонам (по даті доставки)</div>
<div class="report-period">
    Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong>
</div>

{% set total_deliveries = 0 %}
{% set total_sum = 0 %}

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Зона</th>
            <th class="num">К-во доставок</th>
            <th class="num">Сумма заказов</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set deliveries = row.deliveries_count|default(0) %}
                {% set amount = row.orders_sum|default(0) %}
                {% set total_deliveries = total_deliveries + deliveries %}
                {% set total_sum = total_sum + amount %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td>{{ row.zone_name|default('Unknown') }}</td>
                    <td class="num">{{ deliveries }}</td>
                    <td class="num">{{ amount|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
            <tr class="total-row">
                <td colspan="2">Разом</td>
                <td class="num">{{ total_deliveries }}</td>
                <td class="num">{{ total_sum|number_format(2, '.', ' ') }}</td>
            </tr>
        {% else %}
            <tr>
                <td colspan="4">За выбранный период данных нет.</td>
            </tr>
        {% endif %}
    </tbody>
</table>

{% set chart_rows = datasets.chart|default([]) %}
{% set chart_values = [] %}
{% set chart_colors = [] %}
{% set chart_total = 0 %}

{% for row in chart_rows %}
    {% set val = row.deliveries_count|default(0) %}
    {% if val > 0 %}
        {% set chart_values = chart_values|merge([val]) %}
        {% set chart_colors = chart_colors|merge([row.zone_color|default('#4f81bd')]) %}
        {% set chart_total = chart_total + val %}
    {% endif %}
{% endfor %}

{% if chart_values|length %}
<table style="width:100%;border-collapse:collapse;margin-top:14px;">
    <tbody>
        <tr>
            <td style="width:38%;vertical-align:top;">
                <div style="font-size:15px;font-weight:700;text-align:center;margin-bottom:8px;">Доля доставок по зонам</div>
                <div style="width:260px;height:260px;margin:0 auto;">
                    <img alt="chart" src="{{ chart_donut_png(chart_values, chart_colors, chart_total)|e }}" style="display:block;width:260px;height:260px;" />
                </div>
            </td>
            <td style="width:62%;vertical-align:top;">
                <table class="report" style="font-size:11px;">
                    <thead>
                        <tr>
                            <th>Зона</th>
                            <th class="num">К-во</th>
                            <th class="num">Доля, %</th>
                            <th class="num">Сумма</th>
                        </tr>
                    </thead>
                    <tbody>
                        {% for row in chart_rows %}
                            {% set deliveries = row.deliveries_count|default(0) %}
                            {% set share = chart_total > 0 ? (deliveries * 100 / chart_total) : 0 %}
                            <tr>
                                <td><span style="display:inline-block;width:10px;height:10px;background:{{ row.zone_color|default('#4f81bd') }};margin-right:6px;"></span>{{ row.zone_name|default('Unknown') }}</td>
                                <td class="num">{{ deliveries }}</td>
                                <td class="num">{{ share|number_format(2, '.', ' ') }}</td>
                                <td class="num">{{ row.orders_sum|default(0)|number_format(2, '.', ' ') }}</td>
                            </tr>
                        {% endfor %}
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
{% endif %}
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'sales_delivery_zones_daily'],
            [
                'name' => 'Доставки по зонам (дата / зона)',
                'type' => 'report',
                'report_group_id' => $groupId > 0 ? $groupId : null,
                'engine' => 'twig',
                'output_format' => 'pdf',
                'default_paper_preset' => 'a4',
                'default_margin_top_mm' => 8,
                'default_margin_right_mm' => 8,
                'default_margin_bottom_mm' => 8,
                'default_margin_left_mm' => 8,
                'editor_mode' => 'code',
                'css_preset' => 'report_table_default',
                'description' => 'Отчет по доставкам: дата доставки, зона, количество и сумма заказов + диаграмма долей зон.',
                'template_body' => $templateBody,
                'parameters_schema' => json_encode($parametersSchema, JSON_UNESCAPED_UNICODE),
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        DB::table('bs_print_templates')
            ->where('code', 'sales_delivery_zones_daily')
            ->delete();
    }
};
