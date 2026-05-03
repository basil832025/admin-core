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

        $today = $now->toDateString();

        $parametersSchema = [
            [
                'key' => 'date_from',
                'type' => 'date',
                'label' => 'Дата з',
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
                'label' => 'Сайт / джерело',
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
    COALESCE(NULLIF(i.sku, ''), CAST(i.product_id AS CHAR)) AS sku,
    COALESCE(
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(i.product_snapshot, '$.title')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."uk"')), ''),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p.title, '$."ru"')), ''),
        p.slug,
        CONCAT('product#', p.id)
    ) AS title,
    CASE COALESCE(o.payment, 0)
        WHEN 1 THEN 'CARD'
        WHEN 2 THEN 'CASH'
        WHEN 3 THEN 'CLUB'
        WHEN 4 THEN 'ORG'
        WHEN 5 THEN 'FREE'
        WHEN 9 THEN 'POS'
        WHEN 10 THEN 'INVOICE'
        WHEN 11 THEN 'LIQPAY'
        ELSE 'OTHER'
    END AS payment_code,
    SUM(COALESCE(i.qty, 0)) AS qty,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0)), 2) AS gross,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS discount,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS bonuses_spent,
    ROUND(SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_accrued, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))), 2) AS bonuses_accrued,
    ROUND(
        SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0))
        - SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)))
        - SUM(COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))),
        2
    ) AS revenue
FROM bs_shop_orders o
JOIN bs_shop_order_items i ON i.shop_order_id = o.id
LEFT JOIN bs_products p ON p.id = i.product_id
LEFT JOIN (
    SELECT
        a.shop_order_id,
        ABS(SUM(a.amount)) AS discount_abs
    FROM bs_shop_order_adjustments a
    WHERE a.shop_order_item_id IS NULL
      AND a.amount < 0
      AND a.type NOT IN ('loyalty', 'loyalty_spent', 'bonus_spent')
    GROUP BY a.shop_order_id
) od ON od.shop_order_id = o.id
LEFT JOIN (
    SELECT
        lt.order_id,
        ABS(SUM(CASE WHEN lt.type = 'spend' THEN lt.amount ELSE 0 END)) AS bonuses_spent_abs,
        SUM(CASE WHEN lt.type = 'accrual' AND COALESCE(lt.source, '') = 'order' THEN lt.amount ELSE 0 END) AS bonuses_accrued
    FROM bs_loyalty_transactions lt
    GROUP BY lt.order_id
) ob ON ob.order_id = o.id
WHERE o.deleted_at IS NULL
  AND o.status = 'delivered'
  AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN COALESCE(NULLIF(:date_from, ''), CURDATE()) AND COALESCE(NULLIF(:date_to, ''), CURDATE())
  AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
        NULLIF(NULLIF(:brands, ''), 'all'),
        COALESCE(CAST(o.source_id AS CHAR), 'local')
      )
GROUP BY sku, title, payment_code
ORDER BY revenue DESC, title ASC
SQL,
            ],
        ];

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Виторг по касі</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set paymentOrder = ['CASH','CARD','POS','LIQPAY','INVOICE','ORG','CLUB','FREE','OTHER'] %}
{% set paymentLabels = {
  'CASH':'CASH',
  'CARD':'CARD',
  'POS':'POS',
  'LIQPAY':'LIQPAY',
  'INVOICE':'INV',
  'ORG':'ORG',
  'CLUB':'CLUB',
  'FREE':'FREE',
  'OTHER':'OTHER'
} %}

{% set products = {} %}
{% set total_gross = 0 %}
{% set total_discount = 0 %}
{% set total_bonus_spent = 0 %}
{% set total_bonus_accrued = 0 %}
{% set total_revenue = 0 %}
{% set payTotals = {} %}

{% if datasets.main is defined and datasets.main|length %}
  {% for code in paymentOrder %}
    {% set payTotals = payTotals|merge({ (code): 0 }) %}
  {% endfor %}

  {% for row in datasets.main %}
    {% set key = (row.sku|default('') ~ '|' ~ row.title|default('')) %}
    {% if products[key] is not defined %}
      {% set products = products|merge({ (key): {
        'sku': row.sku|default('-'),
        'title': row.title|default('-'),
        'qty': 0,
        'gross': 0,
        'discount': 0,
        'bonuses_spent': 0,
        'bonuses_accrued': 0,
        'revenue': 0,
        'pay': {}
      } }) %}
    {% endif %}

    {% set p = products[key] %}
    {% set code = row.payment_code|default('OTHER') %}
    {% set revenue = row.revenue|default(0) %}
    {% set pay = p.pay|merge({ (code): (p.pay[code]|default(0) + revenue) }) %}

    {% set p = p|merge({
      'qty': p.qty + (row.qty|default(0)),
      'gross': p.gross + (row.gross|default(0)),
      'discount': p.discount + (row.discount|default(0)),
      'bonuses_spent': p.bonuses_spent + (row.bonuses_spent|default(0)),
      'bonuses_accrued': p.bonuses_accrued + (row.bonuses_accrued|default(0)),
      'revenue': p.revenue + revenue,
      'pay': pay
    }) %}
    {% set products = products|merge({ (key): p }) %}

    {% set total_gross = total_gross + (row.gross|default(0)) %}
    {% set total_discount = total_discount + (row.discount|default(0)) %}
    {% set total_bonus_spent = total_bonus_spent + (row.bonuses_spent|default(0)) %}
    {% set total_bonus_accrued = total_bonus_accrued + (row.bonuses_accrued|default(0)) %}
    {% set total_revenue = total_revenue + revenue %}
    {% set payTotals = payTotals|merge({ (code): (payTotals[code]|default(0) + revenue) }) %}
  {% endfor %}
{% endif %}

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th style="width:88px;">Артикул</th>
      <th>Назва</th>
      <th class="num" style="width:70px;">К-сть</th>
      <th class="num" style="width:110px;">Сума</th>
      <th class="num" style="width:110px;">Знижка</th>
      <th class="num" style="width:110px;">Бонуси</th>
      <th class="num" style="width:120px;">Виторг</th>
      {% for code in paymentOrder %}
        <th class="num" style="width:92px;">{{ paymentLabels[code]|default(code) }}</th>
      {% endfor %}
    </tr>
  </thead>
  <tbody>
    {% if products|length %}
      {% for key,p in products %}
        <tr>
          <td>{{ p.sku }}</td>
          <td>{{ p.title }}</td>
          <td class="num">{{ p.qty }}</td>
          <td class="num">{{ p.gross|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ p.discount|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ p.bonuses_spent|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ p.revenue|number_format(2, '.', ' ') }}</td>
          {% for code in paymentOrder %}
            <td class="num">{{ (p.pay[code]|default(0))|number_format(2, '.', ' ') }}</td>
          {% endfor %}
        </tr>
      {% endfor %}
    {% else %}
      <tr><td colspan="{{ 7 + paymentOrder|length }}">Дані за вибраний період відсутні.</td></tr>
    {% endif %}
  </tbody>
</table>

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th colspan="2">Підсумки</th>
      {% for code in paymentOrder %}
        <th class="num">{{ paymentLabels[code]|default(code) }}</th>
      {% endfor %}
    </tr>
  </thead>
  <tbody>
    <tr>
      <td colspan="2"><strong>Загальна сума (без знижок)</strong></td>
      <td class="num" colspan="{{ paymentOrder|length }}">{{ total_gross|number_format(2, '.', ' ') }}</td>
    </tr>
    <tr>
      <td colspan="2"><strong>Сума знижки</strong></td>
      <td class="num" colspan="{{ paymentOrder|length }}">{{ total_discount|number_format(2, '.', ' ') }}</td>
    </tr>
    <tr>
      <td colspan="2"><strong>Списано бонусів</strong></td>
      <td class="num" colspan="{{ paymentOrder|length }}">{{ total_bonus_spent|number_format(2, '.', ' ') }}</td>
    </tr>
    <tr>
      <td colspan="2"><strong>Нараховано бонусів</strong></td>
      <td class="num" colspan="{{ paymentOrder|length }}">{{ total_bonus_accrued|number_format(2, '.', ' ') }}</td>
    </tr>
    <tr class="total-row">
      <td colspan="2"><strong>Виторг по касі</strong></td>
      {% for code in paymentOrder %}
        <td class="num">{{ (payTotals[code]|default(0))|number_format(2, '.', ' ') }}</td>
      {% endfor %}
    </tr>
    <tr class="total-row">
      <td colspan="2"><strong>Разом виторг</strong></td>
      <td class="num" colspan="{{ paymentOrder|length }}">{{ total_revenue|number_format(2, '.', ' ') }}</td>
    </tr>
  </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')->updateOrInsert(
            ['code' => 'cashier_revenue_by_product'],
            [
                'name' => 'Касса: виторг по касі (товари)',
                'type' => 'report',
                'report_group_id' => $groupId > 0 ? $groupId : null,
                'engine' => 'twig',
                'output_format' => 'pdf',
                'default_paper_preset' => 'custom',
                'default_paper_width_mm' => 297,
                'default_paper_height_mm' => 210,
                'default_margin_top_mm' => 7,
                'default_margin_right_mm' => 7,
                'default_margin_bottom_mm' => 7,
                'default_margin_left_mm' => 7,
                'editor_mode' => 'code',
                'css_preset' => 'report_table_dense',
                'description' => 'Виторг по касі з розбивкою по товарам та оплатам (з урахуванням знижок і списаних бонусів).',
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
            ->where('code', 'cashier_revenue_by_product')
            ->delete();
    }
};
