<?php

namespace Database\Seeders;

use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;

class ReceiptTemplatesThreePiesSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            'receipt_kitchen_default',
            'receipt_client_default',
            'receipt_logistic_default',
        ];

        $templates = PrintTemplate::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        foreach ($codes as $code) {
            /** @var PrintTemplate|null $template */
            $template = $templates->get($code);
            if (! $template) {
                continue;
            }

            $dataSources = $this->normalizeDataSources(is_array($template->data_sources) ? $template->data_sources : []);
            $body = $this->normalizeTemplateBody($code, (string) $template->template_body);
            $css = $this->normalizeCss($code, (string) $template->custom_css);

            $template->update([
                'data_sources' => $dataSources,
                'template_body' => $body,
                'custom_css' => $css,
            ]);
        }
    }

    /**
     * @param array<int, mixed> $dataSources
     * @return array<int, mixed>
     */
    private function normalizeDataSources(array $dataSources): array
    {
        foreach ($dataSources as &$source) {
            if (! is_array($source)) {
                continue;
            }

            if (($source['key'] ?? '') !== 'items') {
                continue;
            }

            $query = trim((string) ($source['query'] ?? ''));
            if ($query === '' || str_contains($query, 'product_name_clean')) {
                continue;
            }

            $source['query'] = "SELECT q.*, TRIM(SUBSTRING_INDEX(COALESCE(q.product_name, ''), '[', 1)) AS product_name_clean FROM (\n"
                . $query
                . "\n) q";
        }
        unset($source);

        return $dataSources;
    }

    private function normalizeTemplateBody(string $code, string $body): string
    {
        $body = $this->normalizeProductTitleExpr($body);

        if ($code === 'receipt_kitchen_default') {
            $body = $this->normalizeKitchenDeliveryLine($body);
            $body = str_replace(
                'style="padding-left:3mm;white-space:pre-line;font-style:italic;font-weight:400;"',
                'style="padding-left:3mm;white-space:pre-line;font-style:italic;font-weight:400;font-size:8pt;"',
                $body,
            );
            $body = preg_replace(
                '/<div>\s*<strong>\{\{ item\.qty\|default\(0\) \}\}x/u',
                '<div class="k-item-row">' . "\n" . '<strong>{{ item.qty|default(0) }}x',
                $body,
                1
            ) ?? $body;
        }

        return $body;
    }

    private function normalizeProductTitleExpr(string $body): string
    {
        $body = str_replace(
            [
                "item.product_name|default('Товар')|split('[')[0]",
                "item.product_name|default(\"Товар\")|split(\"[\")[0]",
                "{{ item.product_name|default('Товар') }}{{ size != '' ? (' ' ~ size) : '' }}",
                "{{ item.product_name|default(\"Товар\") }}{{ size != '' ? (' ' ~ size) : '' }}",
            ],
            [
                "item.product_name_clean|default(item.product_name|default('Товар'))",
                "item.product_name_clean|default(item.product_name|default(\"Товар\"))",
                "{{ item.product_name_clean|default(item.product_name|default('Товар')) }}{{ size != '' ? (' ' ~ size) : '' }}",
                "{{ item.product_name_clean|default(item.product_name|default(\"Товар\")) }}{{ size != '' ? (' ' ~ size) : '' }}",
            ],
            $body,
        );

        $body = preg_replace(
            "/\{\{\s*item\.product_name\|default\('Товар'\)\s*\}\}/u",
            "{{ item.product_name_clean|default(item.product_name|default('Товар')) }}",
            $body,
        ) ?? $body;

        $body = preg_replace(
            '/\{\{\s*item\.product_name\|default\("Товар"\)\s*\}\}/u',
            '{{ item.product_name_clean|default(item.product_name|default("Товар")) }}',
            $body,
        ) ?? $body;

        $body = preg_replace(
            "/\{\%\s*set\s+title\s*=\s*\(?\s*item\.product_name\|default\('Товар'\)\s*\)?\s*\%\}/u",
            "{% set title = item.product_name_clean|default(item.product_name|default('Товар')) %}",
            $body,
        ) ?? $body;

        $body = preg_replace(
            '/\{\%\s*set\s+title\s*=\s*\(?\s*item\.product_name\|default\("Товар"\)\s*\)?\s*\%\}/u',
            '{% set title = item.product_name_clean|default(item.product_name|default("Товар")) %}',
            $body,
        ) ?? $body;

        return $body;
    }

    private function normalizeKitchenDeliveryLine(string $body): string
    {
        if (str_contains($body, 'k-delivery-line')) {
            return $body;
        }

        $replacement = <<<'TWIG'
<div class="k-delivery-line">
<strong>Доставка:</strong> <span class="k-delivery-box">{{ delivery_time|default(
        (order.date_order|default("")) ~ " " ~ (order.time_order|default(""))
    ) }}</span>
</div>
TWIG;

        $twoLinePattern = '/<div><strong>Доставка:<\/strong><\/div>\s*<div class="k-delivery-box">\s*\{\{\s*delivery_time\|default\([\s\S]*?\)\s*\}\}\s*<\/div>/u';
        $body = preg_replace($twoLinePattern, $replacement, $body, 1, $count) ?? $body;
        if (($count ?? 0) > 0) {
            return $body;
        }

        $singleLinePattern = '/<div>\s*<strong>Доставка:<\/strong>\s*\{\{\s*delivery_time\|default\([\s\S]*?\)\s*\}\}\s*<\/div>/u';

        return preg_replace($singleLinePattern, $replacement, $body, 1) ?? $body;
    }

    private function normalizeCss(string $code, string $css): string
    {
        return match ($code) {
            'receipt_kitchen_default' => <<<'CSS'
body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #111; line-height: 1.15; }
.lg-title { text-align: center; font-size: 16pt; font-weight: 700; margin: 0 0 1.5mm; border-bottom: 1px dashed #777; padding-bottom: .8mm; }

.lg-meta { width: 100%; border-collapse: collapse; margin: 0 0 1.2mm; }
.lg-meta td { padding: .2mm 0; vertical-align: top; }
.lg-meta td:first-child { width: 33%; color: #333; }
.lg-meta td:last-child { width: 67%; text-align: left; }

.lg-addr, .lg-note { margin: .8mm 0; }
.lg-note { font-weight: 600; }

.lg-items { width: 100%; border-collapse: collapse; margin-top: 1mm; }
.lg-items th { text-align: left; font-weight: 700; border-top: 1px dashed #777; border-bottom: 1px dashed #777; padding: .4mm 0; }
.lg-items td { padding: .45mm 0; border-bottom: 1px dotted #bbb; }
.lg-items .name { width: 58%; }
.lg-items .qty { width: 10%; text-align: center; }
.lg-items .price, .lg-items .sum { width: 16%; text-align: right; white-space: nowrap; }

.lg-discount { margin-top: 1mm; font-weight: 700; }
.lg-total { margin-top: 1.2mm; border-top: 1px dashed #777; padding-top: .8mm; font-size: 15pt; font-weight: 800; letter-spacing: .02em; }
.lg-addr-extra { margin: 0 0 .8mm; font-size: 10pt; color: #222; }

.k-item-row { font-size: 8pt; }

.k-delivery-box { display: inline-block; border: 2px solid #111; padding: 0 .8mm; margin-left: .8mm; font-weight: 700; white-space: nowrap; }

.k-delivery-line { margin: .8mm 0; }
CSS,
            'receipt_client_default' => <<<'CSS'
.logo-print {
  display: block;
  width: 52mm !important;
  height: auto !important;
}
.receipt-header p {
  margin: 0;
  line-height: 1.05;
  text-align: center;
}
.logo-wrap {
  text-align: center;
  margin: 0 0 1mm 0;
  padding: 0;
}

.receipt-header figure {
  margin: 0 0 1mm 0;
}
figure.image,
figure.image_resized {
  margin: 0 !important;
  padding: 0 !important;
  width: auto !important;
  text-align: center;
}
figure.image img,
figure.image_resized img {
  display: inline-block;
  width: 18mm;
  height: auto;
}
.receipt-header .logo-print {
  display: block;
  width: 22mm;
  height: auto;
  margin: 0 auto;
}
.r-order-no{
  text-align:center;
  font-size:11pt;
  margin:1.2mm 0 1mm;
  border-top:1px solid #222;
  border-bottom:1px solid #222;
  padding:.7mm 0;
}
.r-meta{ width:100%; border-collapse:collapse; font-size:10pt; margin:1mm 0; }
.r-meta td{ padding:.2mm 0; vertical-align:top; }
.r-meta .k{ width:31%; font-weight:600; }
.r-meta .v{ width:69%; text-align:left; }
.r-address{ font-size:10pt; margin:1mm 0 1.2mm; }
.r-items{ width:100%; border-collapse:collapse; font-size:9pt; }
.r-items thead th{
  border-top:1px solid #222; border-bottom:1px solid #222;
  font-weight:700; padding:.5mm 0;
}
.r-items td{ border-bottom:2px solid #111; padding:.6mm 0; }
.r-items .name{ text-align:left; width:58%; font-size:7pt; }
.r-items .qty{ text-align:center; width:12%; font-size:7pt; }
.r-items .price,.r-items .sum{ text-align:right; width:15%; font-size:7pt; }
.r-total{
  margin-top:1.3mm; padding-top:.8mm; border-top:1px solid #222;
  display:flex; justify-content:space-between; align-items:flex-end;
  font-size:13pt; font-weight:700;
}
.r-thanks{ margin-top:1.2mm; text-align:center; font-size:9pt; letter-spacing:.04em; }
CSS,
            'receipt_logistic_default' => <<<'CSS'
body { font-family: "DejaVu Sans", sans-serif; font-size: 11pt; color: #111; line-height: 1.15; }
.lg-title { text-align: center; font-size: 16pt; font-weight: 700; margin: 0 0 1.5mm; border-bottom: 1px dashed #777; padding-bottom: .8mm; }

.lg-meta { width: 100%; border-collapse: collapse; margin: 0 0 1.2mm; }
.lg-meta td { padding: .2mm 0; vertical-align: top; }
.lg-meta td:first-child { width: 33%; color: #333; }
.lg-meta td:last-child { width: 67%; text-align: left; }

.lg-addr, .lg-note { margin: .8mm 0; }
.lg-note { font-weight: 600; }

.lg-items { width: 100%; border-collapse: collapse; margin-top: 1mm; font-size: 10pt; }
.lg-items th { text-align: left; font-weight: 700; border-top: 1px dashed #777; border-bottom: 1px dashed #777; padding: .4mm 0; }
.lg-items td { padding: .45mm 0; border-bottom: 2px solid #111; }
.lg-items .name { width: 58%; font-size: 8pt; }
.lg-items .qty { width: 10%; text-align: center; font-size: 8pt; }
.lg-items .price, .lg-items .sum { width: 16%; text-align: right; white-space: nowrap; font-size: 8pt; }

.lg-discount { margin-top: 1mm; font-weight: 700; }
.lg-total { margin-top: 1.2mm; border-top: 1px dashed #777; padding-top: .8mm; font-size: 15pt; font-weight: 800; letter-spacing: .02em; }
.lg-addr-extra { margin: 0 0 .8mm; font-size: 10pt; color: #222; }
CSS,
            default => $css,
        };
    }
}
