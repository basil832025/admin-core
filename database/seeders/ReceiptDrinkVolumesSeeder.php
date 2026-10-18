<?php

namespace Database\Seeders;

use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReceiptDrinkVolumesSeeder extends Seeder
{
    public function run(): void
    {
        if (config('project.name') !== '3piroga' || DB::connection()->getDatabaseName() !== 'myadmin') {
            throw new \RuntimeException('ReceiptDrinkVolumesSeeder requires 3piroga / myadmin.');
        }

        DB::transaction(function (): void {
            $templates = PrintTemplate::query()->whereIn('code', [
                'receipt_kitchen_default', 'receipt_client_default', 'receipt_logistic_default',
            ])->lockForUpdate()->get();

            foreach ($templates as $template) {
                $sources = $template->data_sources ?? [];
                foreach ($sources as &$source) {
                    if (is_array($source) && ($source['key'] ?? '') === 'items') {
                        $source['query'] = $this->updateQuery((string) ($source['query'] ?? ''));
                    }
                }
                unset($source);

                if ($sources !== $template->data_sources) {
                    $template->update(['data_sources' => $sources]);
                }
            }
        });
    }

    public function updateQuery(string $query): string
    {
        if (str_contains($query, '/* receipt_drink_volumes */')) {
            return $query;
        }

        $volume = "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(oi.meta, '$.volume')), ''),\n    " . $this->volumeSql('p');
        $query = str_replace("NULLIF(sz.size_label, '')", "$volume,\n    NULLIF(sz.size_label, '')", $query, $sizeCount);

        $componentVolume = $this->volumeSql('cp');
        // Existing short names may already contain the volume. Keep those intact.
        $suffix = "CASE WHEN CONCAT_WS(' ', cp.short_name, cp.title) REGEXP '[0-9][.,0-9 ]*(мл|ml|л|l)([^[:alpha:]]|$)' "
            . "THEN '' ELSE COALESCE(CONCAT(' (', $componentVolume, ')'), '') END";
        $query = preg_replace_callback(
            "/CONCAT\('Product #', cp\\.id\)(\s*)\)/",
            static fn (array $match): string => $match[0] . ",\n        " . $suffix,
            $query,
            -1,
            $componentCount,
        );

        if ($sizeCount === 0 || $componentCount === 0) {
            throw new \RuntimeException('Unrecognized receipt items query; no templates were changed.');
        }

        return preg_replace('/SELECT/i', 'SELECT /* receipt_drink_volumes */', $query, 1);
    }

    private function volumeSql(string $product): string
    {
        return <<<SQL
COALESCE(
  (SELECT COALESCE(NULLIF(v.value_text, ''),
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(vv.value, '$.uk')), ''),
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(vv.value, '$.ru')), ''),
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(vv.value, '$.en')), ''),
      CAST(v.value_number AS CHAR))
   FROM bs_product_characteristic_value v
   LEFT JOIN bs_characteristic_values vv ON vv.id = v.characteristic_value_id
   LEFT JOIN bs_characteristics vc ON vc.id = COALESCE(v.characteristic_id, vv.characteristic_id)
   WHERE v.product_id IN ($product.id, COALESCE($product.parent_id, $product.id))
     AND vc.slug IN ('volume', 'obiem', 'obyem', 'obem', 'ob-yem', 'ml')
     AND COALESCE(NULLIF(v.value_text, ''), v.value_number, NULLIF(vv.value, '')) IS NOT NULL
   ORDER BY (v.product_id = $product.id) DESC, v.id
   LIMIT 1),
  (SELECT CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM
      CAST(CAST($product.price_unit_quantity AS DECIMAL(12,3)) AS CHAR))), ' ',
      COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(u.short_name, '$.uk')), ''), u.code))
   FROM bs_product_units u
   WHERE u.id = $product.unit_id AND u.code IN ('ml', 'l')
     AND $product.price_unit_quantity > 0)
)
SQL;
    }
}
