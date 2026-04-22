<?php

namespace Database\Seeders;

use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;

class LogisticReceiptExtendedTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $template = PrintTemplate::query()
            ->where('code', 'receipt_logistic_default')
            ->first();

        if (! $template) {
            return;
        }

        $dataSources = is_array($template->data_sources) ? $template->data_sources : [];

        foreach ($dataSources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if (($source['key'] ?? null) !== 'items') {
                continue;
            }

            $source['query'] = <<<'SQL'
SELECT
  oi.id,
  oi.qty,
  oi.sku,
  oi.total,
  oi.kitchen_note,
  COALESCE(
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.title')), ''),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.name')), ''),
    NULLIF(
      CASE
        WHEN JSON_VALID(p.short_name) THEN COALESCE(
          JSON_UNQUOTE(JSON_EXTRACT(p.short_name, '$.uk')),
          JSON_UNQUOTE(JSON_EXTRACT(p.short_name, '$.ru')),
          JSON_UNQUOTE(JSON_EXTRACT(p.short_name, '$.en'))
        )
        ELSE p.short_name
      END,
      ''
    ),
    NULLIF(
      CASE
        WHEN JSON_VALID(p.title) THEN COALESCE(
          JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.uk')),
          JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.ru')),
          JSON_UNQUOTE(JSON_EXTRACT(p.title, '$.en'))
        )
        ELSE p.title
      END,
      ''
    ),
    CONCAT('Product #', oi.product_id)
  ) AS product_name,
  COALESCE(
    NULLIF(sz.size_label, ''),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.weight')), ''),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.size')), ''),
    NULLIF(oi.sku, ''),
    NULLIF(p.sku, ''),
    ''
  ) AS size_label,
  (
    SELECT GROUP_CONCAT(
      CONCAT(
        TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM CAST(pci.qty AS CHAR))),
        ' x ',
        COALESCE(
          NULLIF(
            CASE
              WHEN JSON_VALID(cp.short_name) THEN COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(cp.short_name, '$.uk')),
                JSON_UNQUOTE(JSON_EXTRACT(cp.short_name, '$.ru')),
                JSON_UNQUOTE(JSON_EXTRACT(cp.short_name, '$.en'))
              )
              ELSE cp.short_name
            END,
            ''
          ),
          NULLIF(
            CASE
              WHEN JSON_VALID(cp.title) THEN COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(cp.title, '$.uk')),
                JSON_UNQUOTE(JSON_EXTRACT(cp.title, '$.ru')),
                JSON_UNQUOTE(JSON_EXTRACT(cp.title, '$.en'))
              )
              ELSE cp.title
            END,
            ''
          ),
          CONCAT('Product #', cp.id)
        )
      )
      ORDER BY pci.id
      SEPARATOR '\n'
    )
    FROM bs_product_calculation_items pci
    JOIN bs_products cp
      ON cp.id = pci.component_product_id
    WHERE pci.calculation_id = (
      SELECT pc2.id
      FROM bs_product_calculations pc2
      WHERE pc2.product_id = COALESCE(p.parent_id, p.id)
        AND pc2.valid_from <= COALESCE(o.date_order, DATE(o.created_at))
        AND (pc2.valid_to IS NULL OR pc2.valid_to >= COALESCE(o.date_order, DATE(o.created_at)))
      ORDER BY pc2.valid_from DESC, pc2.id DESC
      LIMIT 1
    )
  ) AS calc_components,
  mods.modifiers_text
FROM bs_shop_order_items oi
JOIN bs_shop_orders o
  ON o.id = oi.shop_order_id
LEFT JOIN bs_products p
  ON p.id = oi.product_id
LEFT JOIN (
  SELECT
    pcv.product_id,
    COALESCE(
      MAX(
        CASE
          WHEN c.slug = 'rozmir-pirogiv' THEN COALESCE(
            NULLIF(pcv.value_text, ''),
            NULLIF(CAST(pcv.value_number AS CHAR), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.uk')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.ru')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.en')), '')
          )
          ELSE NULL
        END
      ),
      MAX(
        CASE
          WHEN c.slug IN ('rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga') THEN COALESCE(
            NULLIF(pcv.value_text, ''),
            NULLIF(CAST(pcv.value_number AS CHAR), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.uk')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.ru')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.en')), '')
          )
          ELSE NULL
        END
      )
    ) AS size_label
  FROM bs_product_characteristic_value pcv
  LEFT JOIN bs_characteristics c
    ON c.id = pcv.characteristic_id
  LEFT JOIN bs_characteristic_values cv
    ON cv.id = pcv.characteristic_value_id
  GROUP BY pcv.product_id
) sz
  ON sz.product_id = oi.product_id
LEFT JOIN (
  SELECT
    pim.order_item_id,
    GROUP_CONCAT(
      CASE
        WHEN pim.type = 'variation' THEN COALESCE(NULLIF(v.name, ''), CONCAT('Варіант #', pim.value_id))
        WHEN pim.type = 'characteristic' THEN CONCAT(
          COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ch.name, '$.uk')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ch.name, '$.ru')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(ch.name, '$.en')), ''),
            CONCAT('Характеристика #', pim.value_id)
          ),
          ': ',
          COALESCE(
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.uk')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.ru')), ''),
            NULLIF(JSON_UNQUOTE(JSON_EXTRACT(cv.value, '$.en')), ''),
            CONCAT('Значення #', pim.value_id)
          )
        )
        ELSE CONCAT('Модифікатор #', pim.value_id)
      END
      ORDER BY pim.id
      SEPARATOR '\n'
    ) AS modifiers_text
  FROM bs_product_item_modifiers pim
  LEFT JOIN bs_product_variation pv
    ON pv.id = pim.value_id
    AND pim.type = 'variation'
  LEFT JOIN bs_variations v
    ON v.id = pv.variation_id
  LEFT JOIN bs_characteristic_values cv
    ON cv.id = pim.value_id
    AND pim.type = 'characteristic'
  LEFT JOIN bs_characteristics ch
    ON ch.id = cv.characteristic_id
  GROUP BY pim.order_item_id
) mods
  ON mods.order_item_id = oi.id
WHERE oi.shop_order_id = :order_id
ORDER BY oi.id ASC
SQL;
        }
        unset($source);

        $templateBody = <<<'TWIG'
{% set order = datasets.order is defined and datasets.order|length ? datasets.order[0] : {} %}

{% set delivery = order.shipping_total|default(0) > 0 ? order.shipping_total|default(0) : order.shipping_price|default(0) %}

{% set discount_raw = order.discount_total|default(0) %}

{% set discount = discount_raw < 0 ? (0 - discount_raw) : discount_raw %}

{% set to_pay = order.grand_total|default(order.total_price_sale|default(order.total_price|default(0))) %}
<div class="lg-title">Заказ
{{ order.order_number|default('-') }}
</div>
<table class="lg-meta">
<tr><td>Принят:</td><td>
{{ order.accepted_at|default('-')|date('d/m/Y H:i') }}
</td></tr>
<tr><td>На время:</td><td>
{{ (order.date_order|default('')) ~ ' ' ~ (order.time_order|default('')) }}
</td></tr>
<tr><td>Курьер:</td><td>
{{ order.self_pickup ? 'Самовивіз' : 'Таксі' }}
</td></tr>
<tr><td>К оплате:</td><td>
{{ to_pay|number_format(2, ',', ' ') }}
</td></tr>
{% if delivery > 0 %}
<tr><td>Доставка:</td><td>
{{ delivery|number_format(2, ',', ' ') }}
</td></tr>
{% endif %}
<tr><td>Конт. имя:</td><td>
{{ order.client_name|default('-') }}
</td></tr>
<tr><td>Конт. тел:</td><td><strong>
{{ order.phone|default('-') }}
</strong></td></tr>
<tr><td>Приборов:</td><td>
{{ order.utensils_count|default('0') }}
</td></tr>
<tr><td>Оплата:</td><td><strong>ГРН.</strong></td></tr>
</table>
<div class="lg-addr">
{{ order.address_line|default('-') }}
</div>
{% if order.entrance|default('') != '' or order.floor|default('') != '' or order.intercom|default('') != '' %}
<div class="lg-addr-extra">
{% if order.entrance|default('') != '' %}
Під'їзд:
{{ order.entrance }}
{% endif %}

{% if order.floor|default('') != '' %}
Поверх:
{{ order.floor }}
{% endif %}

{% if order.intercom|default('') != '' %}
Домофон:
{{ order.intercom }}
{% endif %}
</div>
{% endif %}

{% if order.general_note|default('') != '' %}
<div class="lg-note"><strong>Примечание:</strong>
{{ order.general_note }}
</div>
{% endif %}

{% if order.kitchen_note|default('') != '' %}
<div class="lg-note"><strong>Примечание для кухни:</strong>
{{ order.kitchen_note }}
</div>
{% endif %}

{% if order.delivery_note|default('') != '' %}
<div class="lg-note"><strong>Примітка по доставці:</strong>
{{ order.delivery_note }}
</div>
{% endif %}
<table class="lg-items">
<thead>
<tr>
<th class="name">Наименование</th>
<th class="qty">Кол.</th>
<th class="price">Цена</th>
<th class="sum">Сумма</th>
</tr>
</thead>
<tbody>
{% if datasets.items is defined and datasets.items|length %}

{% for item in datasets.items %}

{% set qty = item.qty|default(0) %}

{% set row_sum = item.total|default(0) %}

{% set unit_price = qty > 0 ? (row_sum / qty) : 0 %}

{% set size = item.size_label|default('') %}
<tr>
<td class="name">
<div style="font-weight:700;">
{{ item.product_name|default('Товар') }}{{ size != '' ? (' ' ~ size) : '' }}
</div>

{% if item.modifiers_text is defined and item.modifiers_text %}
<div style="font-size:11px;line-height:1.35;padding-top:2px;white-space:pre-line;">
{{ item.modifiers_text }}
</div>
{% endif %}

{% if item.kitchen_note is defined and item.kitchen_note %}
<div style="font-size:11px;line-height:1.35;padding-top:2px;white-space:pre-line;">
* {{ item.kitchen_note }}
</div>
{% endif %}

{% if item.calc_components is defined and item.calc_components %}
<div style="font-size:11px;line-height:1.35;padding-top:2px;white-space:pre-line;font-style:italic;font-weight:400;">
{{ item.calc_components }}
</div>
{% endif %}
</td>
<td class="qty">
{{ qty }}
</td>
<td class="price">
{{ unit_price|number_format(2, ',', ' ') }}
</td>
<td class="sum">
{{ row_sum|number_format(2, ',', ' ') }}
</td>
</tr>
{% endfor %}

{% endif %}

{% if delivery > 0 %}
<tr>
<td class="name">Доставка</td>
<td class="qty">1</td>
<td class="price">
{{ delivery|number_format(2, ',', ' ') }}
</td>
<td class="sum">
{{ delivery|number_format(2, ',', ' ') }}
</td>
</tr>
{% endif %}
</tbody>
</table>
{% if discount > 0 %}
<div class="lg-discount">Знижка: -
{{ discount|number_format(2, ',', ' ') }}
</div>
{% endif %}
{% set bonuses_spent = order.bonuses_spent|default(0) %}
{% if bonuses_spent > 0 %}
<div class="lg-discount">Списано бонусів: -{{ bonuses_spent|number_format(2, ',', ' ') }}</div>
{% endif %}
<div class="lg-total">К оплате:
{{ to_pay|number_format(2, ',', ' ') }}
грн</div>
TWIG;

        $template->update([
            'data_sources' => $dataSources,
            'template_body' => $templateBody,
        ]);
    }
}
