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
                'dictionary_query' => <<<'SQL'
SELECT 'all' AS value, 'Всі сайти' AS label
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

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Середній чек</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set summary = (datasets.summary is defined and datasets.summary|length) ? datasets.summary[0] : {} %}

<table class="report" style="margin-bottom: 12px;">
    <thead>
        <tr>
            <th>Показник</th>
            <th class="num">Значення</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Кількість замовлень</td>
            <td class="num">{{ summary.orders_count|default(0) }}</td>
        </tr>
        <tr>
            <td>Виторг</td>
            <td class="num">{{ summary.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
        </tr>
        <tr>
            <td><strong>Середній чек</strong></td>
            <td class="num"><strong>{{ summary.avg_check|default(0)|number_format(2, '.', ' ') }}</strong></td>
        </tr>
        <tr>
            <td>Мінімальний чек</td>
            <td class="num">{{ summary.min_check|default(0)|number_format(2, '.', ' ') }}</td>
        </tr>
        <tr>
            <td>Максимальний чек</td>
            <td class="num">{{ summary.max_check|default(0)|number_format(2, '.', ' ') }}</td>
        </tr>
    </tbody>
</table>

<h4 style="margin:8px 0 6px;">Середній чек по сайтах</h4>
<table class="report" style="margin-bottom: 12px;">
    <thead>
        <tr>
            <th>Сайт / джерело</th>
            <th class="num">Замовлень</th>
            <th class="num">Виторг</th>
            <th class="num">Середній чек</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.by_source is defined and datasets.by_source|length %}
            {% for row in datasets.by_source %}
                <tr>
                    <td>{{ row.source_name|default('-') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ row.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>

<h4 style="margin:8px 0 6px;">Динаміка середнього чеку по днях</h4>
<table class="report">
    <thead>
        <tr>
            <th>Дата</th>
            <th class="num">Замовлень</th>
            <th class="num">Виторг</th>
            <th class="num">Середній чек</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.by_day is defined and datasets.by_day|length %}
            {% for row in datasets.by_day %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ row.revenue_total|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}
        {% else %}
            <tr><td colspan="4">Дані відсутні.</td></tr>
        {% endif %}
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'sales_average_check'],
            [
                'name' => 'Середній чек',
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
                'description' => 'Підсумковий і денний аналіз середнього чеку по періоду.',
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
            ->where('code', 'sales_average_check')
            ->delete();
    }
};
