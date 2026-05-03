<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'cashier_revenue_by_product')
            ->first(['id', 'template_body', 'data_sources']);

        if (! $template) {
            return;
        }

        $sources = json_decode((string) ($template->data_sources ?? ''), true);
        if (! is_array($sources)) {
            $sources = [];
        }

        $query = <<<'SQL'
SELECT
    t.sku,
    t.title,
    SUM(t.qty) AS qty,
    ROUND(SUM(t.gross), 2) AS gross,
    ROUND(SUM(t.discount), 2) AS discount,
    ROUND(SUM(t.bonuses_spent), 2) AS bonuses_spent,
    ROUND(SUM(t.bonuses_accrued), 2) AS bonuses_accrued,
    ROUND(SUM(t.revenue), 2) AS revenue,

    ROUND(SUM(CASE WHEN t.payment_code = 'CASH' THEN t.revenue ELSE 0 END), 2) AS pay_CASH,
    ROUND(SUM(CASE WHEN t.payment_code = 'CARD' THEN t.revenue ELSE 0 END), 2) AS pay_CARD,
    ROUND(SUM(CASE WHEN t.payment_code = 'POS' THEN t.revenue ELSE 0 END), 2) AS pay_POS,
    ROUND(SUM(CASE WHEN t.payment_code = 'LIQPAY' THEN t.revenue ELSE 0 END), 2) AS pay_LIQPAY,
    ROUND(SUM(CASE WHEN t.payment_code = 'INVOICE' THEN t.revenue ELSE 0 END), 2) AS pay_INVOICE,
    ROUND(SUM(CASE WHEN t.payment_code = 'ORG' THEN t.revenue ELSE 0 END), 2) AS pay_ORG,
    ROUND(SUM(CASE WHEN t.payment_code = 'CLUB' THEN t.revenue ELSE 0 END), 2) AS pay_CLUB,
    ROUND(SUM(CASE WHEN t.payment_code = 'FREE' THEN t.revenue ELSE 0 END), 2) AS pay_FREE,
    ROUND(SUM(CASE WHEN t.payment_code = 'OTHER' THEN t.revenue ELSE 0 END), 2) AS pay_OTHER
FROM (
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
        COALESCE(i.qty, 0) AS qty,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) AS gross,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS discount,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS bonuses_spent,
        COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_accrued, 0) / NULLIF(COALESCE(o.subtotal, 0), 0)) AS bonuses_accrued,
        (
            COALESCE(i.subtotal, i.unit_price * i.qty, 0)
            - COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(od.discount_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))
            - COALESCE(i.subtotal, i.unit_price * i.qty, 0) * (COALESCE(ob.bonuses_spent_abs, 0) / NULLIF(COALESCE(o.subtotal, 0), 0))
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
) t
GROUP BY t.sku, t.title
ORDER BY revenue DESC, title ASC
SQL;

        foreach ($sources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if ((string) ($source['key'] ?? '') === 'main' && (string) ($source['type'] ?? '') === 'sql') {
                $source['query'] = $query;
            }
        }
        unset($source);

        $templateBody = <<<'TWIG'
<div class="report-title">Звіт: Виторг по касі</div>
<div class="report-period">Період: <strong>{{ params.date_from|default('-') }}</strong> - <strong>{{ params.date_to|default('-') }}</strong></div>

{% set total_gross = 0 %}
{% set total_discount = 0 %}
{% set total_bonus_spent = 0 %}
{% set total_bonus_accrued = 0 %}
{% set total_revenue = 0 %}
{% set pay_CASH = 0 %}
{% set pay_CARD = 0 %}
{% set pay_POS = 0 %}
{% set pay_LIQPAY = 0 %}
{% set pay_INVOICE = 0 %}
{% set pay_ORG = 0 %}
{% set pay_CLUB = 0 %}
{% set pay_FREE = 0 %}
{% set pay_OTHER = 0 %}

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
      <th class="num" style="width:92px;">CASH</th>
      <th class="num" style="width:92px;">CARD</th>
      <th class="num" style="width:92px;">POS</th>
      <th class="num" style="width:92px;">LIQPAY</th>
      <th class="num" style="width:78px;">INV</th>
      <th class="num" style="width:78px;">ORG</th>
      <th class="num" style="width:86px;">CLUB</th>
      <th class="num" style="width:78px;">FREE</th>
      <th class="num" style="width:92px;">OTHER</th>
    </tr>
  </thead>
  <tbody>
    {% if datasets.main is defined and datasets.main|length %}
      {% for row in datasets.main %}
        {% set total_gross = total_gross + (row.gross|default(0)) %}
        {% set total_discount = total_discount + (row.discount|default(0)) %}
        {% set total_bonus_spent = total_bonus_spent + (row.bonuses_spent|default(0)) %}
        {% set total_bonus_accrued = total_bonus_accrued + (row.bonuses_accrued|default(0)) %}
        {% set total_revenue = total_revenue + (row.revenue|default(0)) %}
        {% set pay_CASH = pay_CASH + (row.pay_CASH|default(0)) %}
        {% set pay_CARD = pay_CARD + (row.pay_CARD|default(0)) %}
        {% set pay_POS = pay_POS + (row.pay_POS|default(0)) %}
        {% set pay_LIQPAY = pay_LIQPAY + (row.pay_LIQPAY|default(0)) %}
        {% set pay_INVOICE = pay_INVOICE + (row.pay_INVOICE|default(0)) %}
        {% set pay_ORG = pay_ORG + (row.pay_ORG|default(0)) %}
        {% set pay_CLUB = pay_CLUB + (row.pay_CLUB|default(0)) %}
        {% set pay_FREE = pay_FREE + (row.pay_FREE|default(0)) %}
        {% set pay_OTHER = pay_OTHER + (row.pay_OTHER|default(0)) %}

        <tr>
          <td>{{ row.sku|default('-') }}</td>
          <td>{{ row.title|default('-') }}</td>
          <td class="num">{{ row.qty|default(0) }}</td>
          <td class="num">{{ (row.gross|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.discount|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.bonuses_spent|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.revenue|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CASH|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CARD|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_POS|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_LIQPAY|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_INVOICE|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_ORG|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_CLUB|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_FREE|default(0))|number_format(2, '.', ' ') }}</td>
          <td class="num">{{ (row.pay_OTHER|default(0))|number_format(2, '.', ' ') }}</td>
        </tr>
      {% endfor %}
    {% else %}
      <tr><td colspan="16">Дані за вибраний період відсутні.</td></tr>
    {% endif %}
  </tbody>
</table>

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th>Підсумки</th>
      <th class="num">Значення</th>
    </tr>
  </thead>
  <tbody>
    <tr><td><strong>Загальна сума (без знижок)</strong></td><td class="num">{{ total_gross|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Сума знижки</strong></td><td class="num">{{ total_discount|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Списано бонусів</strong></td><td class="num">{{ total_bonus_spent|number_format(2, '.', ' ') }}</td></tr>
    <tr><td><strong>Нараховано бонусів</strong></td><td class="num">{{ total_bonus_accrued|number_format(2, '.', ' ') }}</td></tr>
    <tr class="total-row"><td><strong>Виторг по касі</strong></td><td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td></tr>
  </tbody>
</table>

<table class="report" style="margin-top:12px;">
  <thead>
    <tr>
      <th colspan="2">Розшифровка по оплатах (виторг)</th>
    </tr>
  </thead>
  <tbody>
    <tr><td>CASH</td><td class="num">{{ pay_CASH|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>CARD</td><td class="num">{{ pay_CARD|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>POS</td><td class="num">{{ pay_POS|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>LIQPAY</td><td class="num">{{ pay_LIQPAY|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>INVOICE</td><td class="num">{{ pay_INVOICE|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>ORG</td><td class="num">{{ pay_ORG|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>CLUB</td><td class="num">{{ pay_CLUB|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>FREE</td><td class="num">{{ pay_FREE|number_format(2, '.', ' ') }}</td></tr>
    <tr><td>OTHER</td><td class="num">{{ pay_OTHER|number_format(2, '.', ' ') }}</td></tr>
    <tr class="total-row"><td><strong>Разом</strong></td><td class="num">{{ total_revenue|number_format(2, '.', ' ') }}</td></tr>
  </tbody>
</table>
TWIG;

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'template_body' => $templateBody,
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // no-op
    }
};
