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
                'key' => 'received',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
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
            CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.new')) AS DATETIME),
            o.created_at
        ) AS event_ts
    FROM bs_shop_orders o
    WHERE o.deleted_at IS NULL
      AND o.status <> 'cart'
      AND DATE(COALESCE(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.new')) AS DATETIME),
            o.created_at
          )) BETWEEN :date_from AND :date_to
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) q
SQL,
            ],
            [
                'key' => 'delivered',
                'type' => 'sql',
                'connection' => null,
                'enabled' => true,
                'query' => <<<'SQL'
SELECT
    COUNT(*) AS total_orders,
    SUM(CASE WHEN TIME(q.event_ts) >= '09:00:00' AND TIME(q.event_ts) <= '11:59:59' THEN 1 ELSE 0 END) AS slot_0900_1159,
    SUM(CASE WHEN TIME(q.event_ts) >= '12:00:00' AND TIME(q.event_ts) <= '14:00:00' THEN 1 ELSE 0 END) AS slot_1200_1400,
    SUM(CASE WHEN TIME(q.event_ts) >  '14:00:00' AND TIME(q.event_ts) <= '17:59:59' THEN 1 ELSE 0 END) AS slot_1401_1759,
    SUM(CASE WHEN TIME(q.event_ts) >= '18:00:00' AND TIME(q.event_ts) <= '20:00:00' THEN 1 ELSE 0 END) AS slot_1800_2000,
    SUM(CASE WHEN TIME(q.event_ts) <  '09:00:00' OR TIME(q.event_ts) > '20:00:00' THEN 1 ELSE 0 END) AS slot_other
FROM (
    SELECT
        CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME) AS event_ts
    FROM bs_shop_orders o
    WHERE o.deleted_at IS NULL
      AND o.status = 'delivered'
      AND CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME) IS NOT NULL
      AND DATE(CAST(JSON_UNQUOTE(JSON_EXTRACT(o.status_times, '$.delivered')) AS DATETIME)) BETWEEN :date_from AND :date_to
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) q
SQL,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Аналіз часу отримання і доставки замовлень</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set received = (datasets.received is defined and datasets.received|length) ? datasets.received[0] : {} %}
{% set delivered = (datasets.delivered is defined and datasets.delivered|length) ? datasets.delivered[0] : {} %}

<table class="report" style="margin-bottom:12px;">
    <thead>
        <tr>
            <th>К-сть замовлень</th>
            <th class="num">Всього</th>
            <th class="num">09:00-11:59</th>
            <th class="num">12:00-14:00</th>
            <th class="num">14:01-17:59</th>
            <th class="num">18:00-20:00</th>
            <th class="num">Інше</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Оформлення замовлення (NEW)</td>
            <td class="num">{{ received.total_orders|default(0) }}</td>
            <td class="num">{{ received.slot_0900_1159|default(0) }}</td>
            <td class="num">{{ received.slot_1200_1400|default(0) }}</td>
            <td class="num">{{ received.slot_1401_1759|default(0) }}</td>
            <td class="num">{{ received.slot_1800_2000|default(0) }}</td>
            <td class="num">{{ received.slot_other|default(0) }}</td>
        </tr>
        <tr>
            <td>Доставка замовлення (DELIVERED)</td>
            <td class="num">{{ delivered.total_orders|default(0) }}</td>
            <td class="num">{{ delivered.slot_0900_1159|default(0) }}</td>
            <td class="num">{{ delivered.slot_1200_1400|default(0) }}</td>
            <td class="num">{{ delivered.slot_1401_1759|default(0) }}</td>
            <td class="num">{{ delivered.slot_1800_2000|default(0) }}</td>
            <td class="num">{{ delivered.slot_other|default(0) }}</td>
        </tr>
    </tbody>
</table>

{% set labels = ['09:00-11:59', '12:00-14:00', '14:01-17:59', '18:00-20:00', 'Інше'] %}
{% set rec_values = [
    received.slot_0900_1159|default(0),
    received.slot_1200_1400|default(0),
    received.slot_1401_1759|default(0),
    received.slot_1800_2000|default(0),
    received.slot_other|default(0)
] %}
{% set del_values = [
    delivered.slot_0900_1159|default(0),
    delivered.slot_1200_1400|default(0),
    delivered.slot_1401_1759|default(0),
    delivered.slot_1800_2000|default(0),
    delivered.slot_other|default(0)
] %}
{% set colors = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'] %}

<table class="report">
    <thead>
        <tr>
            <th>Інтервал</th>
            <th class="num">Оформлення, шт</th>
            <th class="num">Оформлення, %</th>
            <th class="num">Доставка, шт</th>
            <th class="num">Доставка, %</th>
        </tr>
    </thead>
    <tbody>
        {% for i in 0..4 %}
            {% set rec_v = rec_values[i]|default(0) %}
            {% set del_v = del_values[i]|default(0) %}
            {% set rec_p = received.total_orders|default(0) > 0 ? (rec_v * 100 / received.total_orders) : 0 %}
            {% set del_p = delivered.total_orders|default(0) > 0 ? (del_v * 100 / delivered.total_orders) : 0 %}
            <tr>
                <td><span style="display:inline-block;width:10px;height:10px;background:{{ colors[i] }};border-radius:2px;margin-right:6px;"></span>{{ labels[i] }}</td>
                <td class="num">{{ rec_v }}</td>
                <td class="num">{{ rec_p|number_format(2, '.', ' ') }}</td>
                <td class="num">{{ del_v }}</td>
                <td class="num">{{ del_p|number_format(2, '.', ' ') }}</td>
            </tr>
        {% endfor %}
    </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'sales_receiving_delivery_time_analysis'],
            [
                'name' => 'Аналіз часу отримання і доставки замовлень',
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
                'description' => 'Розподіл кількості замовлень за часовими інтервалами для етапів NEW та DELIVERED.',
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
            ->where('code', 'sales_receiving_delivery_time_analysis')
            ->delete();
    }
};
