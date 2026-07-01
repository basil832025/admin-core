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

        $dataSources = json_decode((string) $template->data_sources, true);
        if (! is_array($dataSources) || ! isset($dataSources[0]) || ! is_array($dataSources[0])) {
            return;
        }

        $dataSources[0]['query'] = <<<'SQL'
SELECT
    t.root_group_id,
    t.root_group_name,
    t.root_group_order,
    t.subgroup_id,
    t.subgroup_name,
    t.subgroup_order,
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
        COALESCE(parent_category.id, category.id, 0) AS root_group_id,
        COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(parent_category.title, '$."uk"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(parent_category.title, '$."ru"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(category.title, '$."uk"')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(category.title, '$."ru"')), ''),
            parent_category.slug,
            category.slug,
            'Без групи'
        ) AS root_group_name,
        COALESCE(parent_category.`order`, category.`order`, 999999) AS root_group_order,
        CASE WHEN parent_category.id IS NOT NULL THEN category.id ELSE NULL END AS subgroup_id,
        CASE
            WHEN parent_category.id IS NULL THEN NULL
            ELSE COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(category.title, '$."uk"')), ''),
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(category.title, '$."ru"')), ''),
                category.slug,
                'Без підгрупи'
            )
        END AS subgroup_name,
        CASE WHEN parent_category.id IS NOT NULL THEN COALESCE(category.`order`, 999999) ELSE 0 END AS subgroup_order,
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
    CROSS JOIN (
        SELECT COALESCE(NULLIF(:status_filter, ''), 'delivered') AS sf
    ) prm
    JOIN bs_shop_order_items i ON i.shop_order_id = o.id
    LEFT JOIN bs_products p ON p.id = i.product_id
    LEFT JOIN bs_products parent_product ON parent_product.id = p.parent_id
    LEFT JOIN bs_product_categories category ON category.id = COALESCE(p.category_id, parent_product.category_id)
    LEFT JOIN bs_product_categories parent_category
        ON parent_category.id = category.parent_id
       AND category.parent_id > 0
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
      AND o.status <> 'cart'
      AND (
            (prm.sf = 'all_active' AND o.status <> 'cancelled')
            OR (prm.sf <> 'all_active' AND o.status = prm.sf)
          )
      AND DATE(COALESCE(o.date_order, o.created_at)) BETWEEN DATE(COALESCE(NULLIF(:date_from, ''), CURDATE())) AND DATE(COALESCE(NULLIF(:date_to, ''), CURDATE()))
      AND COALESCE(CAST(o.source_id AS CHAR), 'local') = COALESCE(
            NULLIF(NULLIF(:brands, ''), 'all'),
            COALESCE(CAST(o.source_id AS CHAR), 'local')
          )
) t
GROUP BY
    t.root_group_id,
    t.root_group_name,
    t.root_group_order,
    t.subgroup_id,
    t.subgroup_name,
    t.subgroup_order,
    t.sku,
    t.title
ORDER BY
    t.root_group_order,
    t.root_group_name,
    t.subgroup_order,
    t.subgroup_name,
    revenue DESC,
    t.title
SQL;

        $groupedBody = <<<'TWIG'
  <tbody>
    {% set current_root = null %}
    {% set current_subgroup = null %}
    {% set root_qty = 0 %}
    {% set root_gross = 0 %}
    {% set root_discount = 0 %}
    {% set root_bonuses = 0 %}
    {% set root_revenue = 0 %}
    {% set root_CASH = 0 %}
    {% set root_CARD = 0 %}
    {% set root_POS = 0 %}
    {% set root_LIQPAY = 0 %}
    {% set root_INVOICE = 0 %}
    {% set root_ORG = 0 %}
    {% set root_CLUB = 0 %}
    {% set root_FREE = 0 %}
    {% set root_OTHER = 0 %}
    {% set subgroup_qty = 0 %}
    {% set subgroup_gross = 0 %}
    {% set subgroup_discount = 0 %}
    {% set subgroup_bonuses = 0 %}
    {% set subgroup_revenue = 0 %}
    {% set subgroup_CASH = 0 %}
    {% set subgroup_CARD = 0 %}
    {% set subgroup_POS = 0 %}
    {% set subgroup_LIQPAY = 0 %}
    {% set subgroup_INVOICE = 0 %}
    {% set subgroup_ORG = 0 %}
    {% set subgroup_CLUB = 0 %}
    {% set subgroup_FREE = 0 %}
    {% set subgroup_OTHER = 0 %}

    {% if datasets.main is defined and datasets.main|length %}
      {% for row in datasets.main %}
        {% if current_root != row.root_group_id %}
          {% if current_root is not null %}
            {% if current_subgroup %}
              <tr style="background:#fff7ed;font-weight:700;">
                <td colspan="2">Разом по підгрупі: {{ current_subgroup_name }}</td>
                <td class="num">{{ subgroup_qty }}</td><td class="num">{{ subgroup_gross|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_discount|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_bonuses|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_revenue|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CASH|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CARD|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_POS|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_LIQPAY|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_INVOICE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_ORG|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CLUB|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_FREE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_OTHER|number_format(2, '.', ' ') }}</td>
              </tr>
            {% endif %}
            <tr style="background:#dbeafe;font-weight:700;">
              <td colspan="2">Разом: {{ current_root_name }}</td>
              <td class="num">{{ root_qty }}</td><td class="num">{{ root_gross|number_format(2, '.', ' ') }}</td><td class="num">{{ root_discount|number_format(2, '.', ' ') }}</td><td class="num">{{ root_bonuses|number_format(2, '.', ' ') }}</td><td class="num">{{ root_revenue|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CASH|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CARD|number_format(2, '.', ' ') }}</td><td class="num">{{ root_POS|number_format(2, '.', ' ') }}</td><td class="num">{{ root_LIQPAY|number_format(2, '.', ' ') }}</td><td class="num">{{ root_INVOICE|number_format(2, '.', ' ') }}</td><td class="num">{{ root_ORG|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CLUB|number_format(2, '.', ' ') }}</td><td class="num">{{ root_FREE|number_format(2, '.', ' ') }}</td><td class="num">{{ root_OTHER|number_format(2, '.', ' ') }}</td>
            </tr>
          {% endif %}

          {% set current_root = row.root_group_id %}
          {% set current_root_name = row.root_group_name %}
          {% set current_subgroup = null %}
          {% set root_qty = 0 %}{% set root_gross = 0 %}{% set root_discount = 0 %}{% set root_bonuses = 0 %}{% set root_revenue = 0 %}
          {% set root_CASH = 0 %}{% set root_CARD = 0 %}{% set root_POS = 0 %}{% set root_LIQPAY = 0 %}{% set root_INVOICE = 0 %}{% set root_ORG = 0 %}{% set root_CLUB = 0 %}{% set root_FREE = 0 %}{% set root_OTHER = 0 %}
          <tr style="background:#bfdbfe;font-size:13px;font-weight:700;"><td colspan="16">{{ row.root_group_name }}</td></tr>
        {% endif %}

        {% if row.subgroup_id and current_subgroup != row.subgroup_id %}
          {% if current_subgroup %}
            <tr style="background:#fff7ed;font-weight:700;">
              <td colspan="2">Разом по підгрупі: {{ current_subgroup_name }}</td>
              <td class="num">{{ subgroup_qty }}</td><td class="num">{{ subgroup_gross|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_discount|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_bonuses|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_revenue|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CASH|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CARD|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_POS|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_LIQPAY|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_INVOICE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_ORG|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CLUB|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_FREE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_OTHER|number_format(2, '.', ' ') }}</td>
            </tr>
          {% endif %}
          {% set current_subgroup = row.subgroup_id %}
          {% set current_subgroup_name = row.subgroup_name %}
          {% set subgroup_qty = 0 %}{% set subgroup_gross = 0 %}{% set subgroup_discount = 0 %}{% set subgroup_bonuses = 0 %}{% set subgroup_revenue = 0 %}
          {% set subgroup_CASH = 0 %}{% set subgroup_CARD = 0 %}{% set subgroup_POS = 0 %}{% set subgroup_LIQPAY = 0 %}{% set subgroup_INVOICE = 0 %}{% set subgroup_ORG = 0 %}{% set subgroup_CLUB = 0 %}{% set subgroup_FREE = 0 %}{% set subgroup_OTHER = 0 %}
          <tr style="background:#ffedd5;font-weight:700;"><td colspan="16">{{ row.subgroup_name }}</td></tr>
        {% endif %}

        {% set total_qty = total_qty + (row.qty|default(0)) %}
        {% set total_gross = total_gross + (row.gross|default(0)) %}
        {% set total_discount = total_discount + (row.discount|default(0)) %}
        {% set total_bonus_spent = total_bonus_spent + (row.bonuses_spent|default(0)) %}
        {% set total_bonus_accrued = total_bonus_accrued + (row.bonuses_accrued|default(0)) %}
        {% set total_revenue = total_revenue + (row.revenue|default(0)) %}
        {% set pay_CASH = pay_CASH + (row.pay_CASH|default(0)) %}{% set pay_CARD = pay_CARD + (row.pay_CARD|default(0)) %}{% set pay_POS = pay_POS + (row.pay_POS|default(0)) %}{% set pay_LIQPAY = pay_LIQPAY + (row.pay_LIQPAY|default(0)) %}{% set pay_INVOICE = pay_INVOICE + (row.pay_INVOICE|default(0)) %}{% set pay_ORG = pay_ORG + (row.pay_ORG|default(0)) %}{% set pay_CLUB = pay_CLUB + (row.pay_CLUB|default(0)) %}{% set pay_FREE = pay_FREE + (row.pay_FREE|default(0)) %}{% set pay_OTHER = pay_OTHER + (row.pay_OTHER|default(0)) %}

        {% set root_qty = root_qty + (row.qty|default(0)) %}{% set root_gross = root_gross + (row.gross|default(0)) %}{% set root_discount = root_discount + (row.discount|default(0)) %}{% set root_bonuses = root_bonuses + (row.bonuses_spent|default(0)) %}{% set root_revenue = root_revenue + (row.revenue|default(0)) %}
        {% set root_CASH = root_CASH + (row.pay_CASH|default(0)) %}{% set root_CARD = root_CARD + (row.pay_CARD|default(0)) %}{% set root_POS = root_POS + (row.pay_POS|default(0)) %}{% set root_LIQPAY = root_LIQPAY + (row.pay_LIQPAY|default(0)) %}{% set root_INVOICE = root_INVOICE + (row.pay_INVOICE|default(0)) %}{% set root_ORG = root_ORG + (row.pay_ORG|default(0)) %}{% set root_CLUB = root_CLUB + (row.pay_CLUB|default(0)) %}{% set root_FREE = root_FREE + (row.pay_FREE|default(0)) %}{% set root_OTHER = root_OTHER + (row.pay_OTHER|default(0)) %}

        {% if row.subgroup_id %}
          {% set subgroup_qty = subgroup_qty + (row.qty|default(0)) %}{% set subgroup_gross = subgroup_gross + (row.gross|default(0)) %}{% set subgroup_discount = subgroup_discount + (row.discount|default(0)) %}{% set subgroup_bonuses = subgroup_bonuses + (row.bonuses_spent|default(0)) %}{% set subgroup_revenue = subgroup_revenue + (row.revenue|default(0)) %}
          {% set subgroup_CASH = subgroup_CASH + (row.pay_CASH|default(0)) %}{% set subgroup_CARD = subgroup_CARD + (row.pay_CARD|default(0)) %}{% set subgroup_POS = subgroup_POS + (row.pay_POS|default(0)) %}{% set subgroup_LIQPAY = subgroup_LIQPAY + (row.pay_LIQPAY|default(0)) %}{% set subgroup_INVOICE = subgroup_INVOICE + (row.pay_INVOICE|default(0)) %}{% set subgroup_ORG = subgroup_ORG + (row.pay_ORG|default(0)) %}{% set subgroup_CLUB = subgroup_CLUB + (row.pay_CLUB|default(0)) %}{% set subgroup_FREE = subgroup_FREE + (row.pay_FREE|default(0)) %}{% set subgroup_OTHER = subgroup_OTHER + (row.pay_OTHER|default(0)) %}
        {% endif %}

        <tr>
          <td>{{ row.sku|default('-') }}</td><td>{{ row.title|default('-') }}</td><td class="num">{{ row.qty|default(0) }}</td><td class="num">{{ (row.gross|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.discount|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.bonuses_spent|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.revenue|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_CASH|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_CARD|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_POS|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_LIQPAY|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_INVOICE|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_ORG|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_CLUB|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_FREE|default(0))|number_format(2, '.', ' ') }}</td><td class="num">{{ (row.pay_OTHER|default(0))|number_format(2, '.', ' ') }}</td>
        </tr>

        {% if loop.last %}
          {% if current_subgroup %}
            <tr style="background:#fff7ed;font-weight:700;"><td colspan="2">Разом по підгрупі: {{ current_subgroup_name }}</td><td class="num">{{ subgroup_qty }}</td><td class="num">{{ subgroup_gross|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_discount|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_bonuses|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_revenue|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CASH|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CARD|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_POS|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_LIQPAY|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_INVOICE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_ORG|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_CLUB|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_FREE|number_format(2, '.', ' ') }}</td><td class="num">{{ subgroup_OTHER|number_format(2, '.', ' ') }}</td></tr>
          {% endif %}
          <tr style="background:#dbeafe;font-weight:700;"><td colspan="2">Разом: {{ current_root_name }}</td><td class="num">{{ root_qty }}</td><td class="num">{{ root_gross|number_format(2, '.', ' ') }}</td><td class="num">{{ root_discount|number_format(2, '.', ' ') }}</td><td class="num">{{ root_bonuses|number_format(2, '.', ' ') }}</td><td class="num">{{ root_revenue|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CASH|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CARD|number_format(2, '.', ' ') }}</td><td class="num">{{ root_POS|number_format(2, '.', ' ') }}</td><td class="num">{{ root_LIQPAY|number_format(2, '.', ' ') }}</td><td class="num">{{ root_INVOICE|number_format(2, '.', ' ') }}</td><td class="num">{{ root_ORG|number_format(2, '.', ' ') }}</td><td class="num">{{ root_CLUB|number_format(2, '.', ' ') }}</td><td class="num">{{ root_FREE|number_format(2, '.', ' ') }}</td><td class="num">{{ root_OTHER|number_format(2, '.', ' ') }}</td></tr>
        {% endif %}
      {% endfor %}
    {% else %}
      <tr><td colspan="16">Дані за вибраний період відсутні.</td></tr>
    {% endif %}
  </tbody>
TWIG;

        $templateBody = preg_replace(
            '/  <tbody>.*?  <\/tbody>/s',
            $groupedBody,
            (string) $template->template_body,
            1
        );

        if (! is_string($templateBody)) {
            return;
        }

        DB::table('bs_print_templates')
            ->where('id', (int) $template->id)
            ->update([
                'data_sources' => json_encode($dataSources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'template_body' => $templateBody,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Report definitions are data; rollback intentionally leaves the latest working version.
    }
};