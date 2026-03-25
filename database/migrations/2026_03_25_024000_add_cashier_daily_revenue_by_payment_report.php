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

        $today = $now->toDateString();

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата с',
                'default' => $today,
                'required' => true,
            ],
            [
                'key' => 'date_to',
                'type' => 'date',
                'label' => 'Дата по',
                'default' => $today,
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
            [
                'key' => 'status_filter',
                'type' => 'select',
                'label' => 'Статус заказов',
                'default' => 'delivered',
                'required' => true,
                'options' => [
                    'delivered' => 'Завершенные (по умолчанию)',
                    'all_active' => 'Все статусы (кроме корзины и отмененных)',
                    'new' => 'Новые',
                    'processing' => 'В обработке',
                    'on_hold' => 'Отложенные',
                    'filling' => 'Начинка',
                    'molding' => 'Лепка',
                    'baking' => 'Печь',
                    'prepared' => 'Приготовлен',
                    'assembled' => 'Собран',
                    'shipped' => 'В пути',
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
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN COALESCE(NULLIF(:date_from, ''), CURDATE()) AND COALESCE(NULLIF(:date_to, ''), CURDATE())
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
  AND (
        COALESCE(NULLIF(:status_filter, ''), 'delivered') = 'all_active'
        OR o.status = COALESCE(NULLIF(:status_filter, ''), 'delivered')
      )
GROUP BY report_date, payment_method
ORDER BY report_date ASC, revenue_total DESC, payment_method ASC
SQL,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Отчет кассира: выручка по типам оплат</div>
<div class="report-period">
    Период: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong>
</div>

{% set total_orders = 0 %}
{% set total_revenue = 0 %}

<table class="report" style="margin-top:12px;">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Тип оплаты</th>
            <th class="num">Кол-во заказов</th>
            <th class="num">Сумма выручки</th>
            <th class="num">Средний чек</th>
            <th class="num">Доля, %</th>
        </tr>
    </thead>
    <tbody>
        {% if datasets.main is defined and datasets.main|length %}
            {% for row in datasets.main %}
                {% set total_orders = total_orders + (row.orders_count|default(0)) %}
                {% set total_revenue = total_revenue + (row.revenue_total|default(0)) %}
            {% endfor %}

            {% for row in datasets.main %}
                {% set revenue = row.revenue_total|default(0) %}
                <tr>
                    <td>{{ row.report_date|default('-') }}</td>
                    <td>{{ row.payment_method|default('Не указан') }}</td>
                    <td class="num">{{ row.orders_count|default(0) }}</td>
                    <td class="num">{{ revenue|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ row.avg_check|default(0)|number_format(2, '.', ' ') }}</td>
                    <td class="num">{{ (total_revenue > 0 ? (revenue * 100 / total_revenue) : 0)|number_format(2, '.', ' ') }}</td>
                </tr>
            {% endfor %}

            <tr class="total-row">
                <td colspan="2">Итого</td>
                <td class="num">{{ total_orders }}</td>
                <td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td>
                <td class="num">{{ (total_orders > 0 ? (total_revenue / total_orders) : 0)|number_format(2, '.', ' ') }}</td>
                <td class="num">100.00</td>
            </tr>
        {% else %}
            <tr><td colspan="6">За выбранный период данных нет.</td></tr>
        {% endif %}
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'cashier_daily_revenue_by_payment'],
            [
                'name' => 'Касса: выручка по типам оплат',
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
                'description' => 'Удобный отчет для кассира: выручка за день/период по типам оплат.',
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
            ->where('code', 'cashier_daily_revenue_by_payment')
            ->delete();
    }
};
