<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('bs_print_templates')
            ->where('code', 'receipt_kitchen_default')
            ->first(['id', 'template_body', 'data_sources']);

        if (! $template) {
            return;
        }

        $sources = json_decode((string) $template->data_sources, true);
        if (! is_array($sources)) {
            $sources = [];
        }

        $itemsQuery = <<<'SQL'
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
  ) AS calc_components
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
WHERE oi.shop_order_id = :order_id
ORDER BY oi.id ASC
SQL;

        foreach ($sources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if ((string) ($source['key'] ?? '') === 'items' && (string) ($source['type'] ?? 'sql') === 'sql') {
                $source['query'] = $itemsQuery;
            }
        }
        unset($source);

        $body = (string) $template->template_body;
        if (! str_contains($body, 'item.calc_components')) {
            $calcBlock = <<<'TWIG'
{% if item.calc_components is defined and item.calc_components %}
<div style="padding-left:3mm;white-space:pre-line;">
{{ item.calc_components }}
</div>
{% endif %}
TWIG;

            $needle = "{% endif %}\n\n{% endfor %}";
            if (str_contains($body, $needle)) {
                $body = str_replace($needle, "{% endif %}\n\n{$calcBlock}\n\n{% endfor %}", $body);
            } else {
                $body = preg_replace('/\{%\s*endfor\s*%\}/', $calcBlock . "\n\n{% endfor %}", $body, 1) ?? $body;
            }
        }

        DB::table('bs_print_templates')
            ->where('id', $template->id)
            ->update([
                'data_sources' => json_encode($sources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'template_body' => $body,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally left without rollback to avoid overwriting manual template edits.
    }
};
