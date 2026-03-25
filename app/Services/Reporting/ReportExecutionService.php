<?php

namespace App\Services\Reporting;

use App\Models\PrintTemplate;
use App\Services\Printing\TwigTemplateRenderService;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExecutionService
{
    public function __construct(private readonly TwigTemplateRenderService $renderer) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{html: string, styled_html: string, datasets: array<string, array<int|string, mixed>>}
     */
    public function execute(PrintTemplate $template, array $params): array
    {
        $rendered = $this->renderer->renderTemplate($template, $params, []);
        $html = (string) ($rendered['html'] ?? '');
        $css = $this->resolveTemplateCss($template);

        return [
            'html' => $html,
            'styled_html' => $this->injectCssIntoBodyHtml($html, $css),
            'datasets' => (array) ($rendered['datasets'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function exportPdf(PrintTemplate $template, array $params): string
    {
        $result = $this->execute($template, $params);
        $layout = $this->resolveLayoutFromTemplate($template);
        $bodyHtml = $this->normalizeHtmlForPdf((string) ($result['html'] ?? ''));
        $css = $this->resolveTemplateCss($template);

        $html = '<!doctype html><html><head><meta charset="UTF-8">'
            .($css !== '' ? '<style>'.$css.'</style>' : '')
            .'<style>@page{margin:0;}body{margin:0;padding:0;}</style>'
            .'</head><body>'
            .'<div style="padding:'
            .$layout['margin_top_mm'].'mm '
            .$layout['margin_right_mm'].'mm '
            .$layout['margin_bottom_mm'].'mm '
            .$layout['margin_left_mm'].'mm;'
            .'font-family:\'DejaVu Sans\',sans-serif;">'
            .$bodyHtml
            .'</div></body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper([
            0,
            0,
            $layout['width_mm'] * 2.834645669,
            $layout['height_mm'] * 2.834645669,
        ]);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function exportExcel(PrintTemplate $template, array $params): string
    {
        $result = $this->execute($template, $params);
        $datasets = (array) ($result['datasets'] ?? []);
        $reportHeaders = $this->extractHeadersFromHtml((string) ($result['html'] ?? ''));
        $templateColumnKeys = $this->extractColumnKeysFromTemplateBody((string) ($template->template_body ?? ''));
        $tableFromHtml = $this->extractFirstTableFromHtml((string) ($result['html'] ?? ''));

        $spreadsheet = new Spreadsheet;
        $sheetIndex = 0;

        if ($tableFromHtml !== null && $tableFromHtml['headers'] !== []) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');

            $columnIndex = 1;
            foreach ($tableFromHtml['headers'] as $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex).'1', $header);
                $columnIndex++;
            }

            $rowIndex = 2;
            foreach ($tableFromHtml['rows'] as $row) {
                $columnIndex = 1;
                foreach ($row as $value) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex).(string) $rowIndex, $value);
                    $columnIndex++;
                }
                $rowIndex++;
            }

            $this->appendChartsForTemplate($sheet, $template, $datasets, $rowIndex + 1);

            ob_start();
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');

            return (string) ob_get_clean();
        }

        if ($datasets === []) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Report');
            $sheet->setCellValue('A1', 'No data');
        } else {
            foreach ($datasets as $key => $rows) {
                $rows = is_array($rows) ? $rows : [];
                $sheet = $sheetIndex === 0
                    ? $spreadsheet->getActiveSheet()
                    : $spreadsheet->createSheet($sheetIndex);

                $title = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $key) ?: 'Sheet_'.$sheetIndex;
                $sheet->setTitle(substr($title, 0, 30));

                $normalizedRows = [];
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $normalizedRows[] = $row;
                    }
                }

                if ($normalizedRows === []) {
                    $sheet->setCellValue('A1', 'No data');
                    $sheetIndex++;

                    continue;
                }

                $headers = [];
                foreach ($normalizedRows as $row) {
                    foreach (array_keys($row) as $column) {
                        $column = (string) $column;
                        if (! in_array($column, $headers, true)) {
                            $headers[] = $column;
                        }
                    }
                }

                $headerLabels = $headers;
                $exportKeys = $headers;

                if ($reportHeaders !== []) {
                    $headerLabels = $reportHeaders;

                    if ($templateColumnKeys !== []) {
                        $usableKeys = array_values(array_filter(
                            $templateColumnKeys,
                            static fn (string $key): bool => in_array($key, $headers, true)
                        ));

                        if ($usableKeys !== []) {
                            $exportKeys = $usableKeys;
                        }
                    }

                    if (count($exportKeys) > count($headerLabels)) {
                        $exportKeys = array_slice($exportKeys, 0, count($headerLabels));
                    } elseif (count($exportKeys) < count($headerLabels)) {
                        $headerLabels = array_slice($headerLabels, 0, count($exportKeys));
                    }
                }

                $columnIndex = 1;
                foreach ($headerLabels as $header) {
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex).'1', $header);
                    $columnIndex++;
                }

                $rowIndex = 2;
                foreach ($normalizedRows as $row) {
                    $columnIndex = 1;
                    foreach ($exportKeys as $headerKey) {
                        $value = $row[$headerKey] ?? null;
                        $sheet->setCellValue(
                            Coordinate::stringFromColumnIndex($columnIndex).(string) $rowIndex,
                            is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)
                        );
                        $columnIndex++;
                    }
                    $rowIndex++;
                }

                $sheetIndex++;
            }
        }

        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @return array<int, string>
     */
    private function extractHeadersFromHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }
        $headers = [];
        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/is', $html, $matches);

        foreach ((array) ($matches[1] ?? []) as $rawHeader) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $rawHeader), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($text !== '') {
                $headers[] = $text;
            }
        }

        return $headers;
    }

    /**
     * @return array<int, string>
     */
    private function extractColumnKeysFromTemplateBody(string $templateBody): array
    {
        if (trim($templateBody) === '') {
            return [];
        }

        preg_match_all('/<td[^>]*>.*?\brow\.([a-zA-Z_][a-zA-Z0-9_]*)\b.*?<\/td>/is', $templateBody, $cellMatches);
        $keysFromCells = array_values(array_unique($cellMatches[1] ?? []));
        if ($keysFromCells !== []) {
            return $keysFromCells;
        }

        preg_match_all('/\brow\.([a-zA-Z_][a-zA-Z0-9_]*)\b/', $templateBody, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}|null
     */
    private function extractFirstTableFromHtml(string $html): ?array
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        if (! $loaded) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $tableNode = $xpath->query('//table[1]')->item(0);
        if (! $tableNode) {
            return null;
        }

        $headers = [];
        $headerNodes = $xpath->query('.//thead//th', $tableNode);
        if ($headerNodes && $headerNodes->length > 0) {
            foreach ($headerNodes as $node) {
                $text = trim(preg_replace('/\s+/u', ' ', (string) $node->textContent) ?? '');
                if ($text !== '') {
                    $headers[] = $text;
                }
            }
        }

        $rows = [];
        $rowNodes = $xpath->query('.//tbody//tr', $tableNode);
        if ($rowNodes && $rowNodes->length > 0) {
            foreach ($rowNodes as $tr) {
                $cells = [];
                $cellNodes = $xpath->query('.//td|.//th', $tr);
                if (! $cellNodes) {
                    continue;
                }

                foreach ($cellNodes as $cell) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', (string) $cell->textContent) ?? '');
                }

                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function injectCssIntoBodyHtml(string $bodyHtml, string $css): string
    {
        if (trim($css) === '') {
            return $bodyHtml;
        }

        return '<style>'.$css.'</style>'.$bodyHtml;
    }

    private function resolveTemplateCss(PrintTemplate $template): string
    {
        $presetCss = $this->presetCss((string) ($template->css_preset ?? 'none'));
        $customCss = trim((string) ($template->custom_css ?? ''));

        if ($presetCss === '') {
            return $customCss;
        }

        if ($customCss === '') {
            return $presetCss;
        }

        return $presetCss."\n\n".$customCss;
    }

    private function presetCss(string $preset): string
    {
        return match ($preset) {
            'report_table_default' => 'table.report{width:100%;border-collapse:collapse;font-family:"DejaVu Sans",sans-serif;font-size:12px;line-height:1.35;color:#0f172a;} .report caption{caption-side:top;text-align:left;font-weight:700;font-size:13px;color:#1e3a8a;margin:0 0 8px;} .report th,.report td{border:1px solid #dbe5f3;padding:7px 10px;vertical-align:top;} .report thead th{background:#eaf2ff;color:#1e3a8a;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:.02em;} .report tbody tr:nth-child(even) td{background:#f8fbff;} .report tbody tr td:first-child{font-weight:600;color:#334155;} .report tfoot td{background:#eef2ff;font-weight:700;color:#1e3a8a;border-top:2px solid #93c5fd;} .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;} .total-row td{font-weight:700;background:#eef2ff;border-top:2px solid #93c5fd;color:#1e3a8a;}',
            'report_table_dense' => 'table.report{width:100%;border-collapse:collapse;font-family:"DejaVu Sans",sans-serif;font-size:10.5px;line-height:1.25;color:#111827;} .report caption{caption-side:top;text-align:left;font-weight:700;font-size:11.5px;color:#1d4ed8;margin:0 0 6px;} .report th,.report td{border:1px solid #d7dfec;padding:4px 6px;vertical-align:top;} .report thead th{background:#eff6ff;color:#1e40af;font-weight:700;text-align:left;} .report tbody tr:nth-child(even) td{background:#f9fbff;} .report tbody tr td:first-child{font-weight:600;color:#374151;} .report tfoot td{background:#eef4ff;font-weight:700;color:#1e3a8a;border-top:2px solid #9db7ea;} .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;} .total-row td{font-weight:700;background:#eef4ff;border-top:2px solid #9db7ea;color:#1e3a8a;}',
            'receipt_compact' => 'body{font-size:10pt;line-height:1.2;} table{width:100%;border-collapse:collapse;} td,th{padding:2px 0;} .center{text-align:center;} .right{text-align:right;}',
            default => '',
        };
    }

    /**
     * @return array{width_mm: float, height_mm: float, margin_top_mm: float, margin_right_mm: float, margin_bottom_mm: float, margin_left_mm: float}
     */
    private function resolveLayoutFromTemplate(PrintTemplate $template): array
    {
        [$width, $height] = $this->paperPresetToSize(
            (string) ($template->default_paper_preset ?? 'a4'),
            (float) ($template->default_paper_width_mm ?? 0),
            (float) ($template->default_paper_height_mm ?? 0),
        );

        return [
            'width_mm' => $width,
            'height_mm' => $height,
            'margin_top_mm' => max(0, (float) ($template->default_margin_top_mm ?? 3)),
            'margin_right_mm' => max(0, (float) ($template->default_margin_right_mm ?? 2)),
            'margin_bottom_mm' => max(0, (float) ($template->default_margin_bottom_mm ?? 3)),
            'margin_left_mm' => max(0, (float) ($template->default_margin_left_mm ?? 2)),
        ];
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function paperPresetToSize(string $preset, float $customWidth = 0, float $customHeight = 0): array
    {
        return match ($preset) {
            'a5' => [148.0, 210.0],
            'thermal_80' => [80.0, 3650.0],
            'thermal_72' => [72.0, 3650.0],
            'thermal_58' => [58.0, 3650.0],
            'custom' => [
                $customWidth > 20 ? $customWidth : 210.0,
                $customHeight > 20 ? $customHeight : 297.0,
            ],
            default => [210.0, 297.0],
        };
    }

    private function normalizeHtmlForPdf(string $html): string
    {
        return preg_replace_callback(
            '/<img\b[^>]*\bsrc=("|\')(.*?)\1[^>]*>/i',
            function (array $matches): string {
                $tag = $matches[0];
                $src = $matches[2];
                $resolved = $this->resolvePdfImageSrc($src);

                return str_replace($src, $resolved, $tag);
            },
            $html
        ) ?? $html;
    }

    private function resolvePdfImageSrc(string $src): string
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5));
        if ($src === '' || str_starts_with($src, 'data:') || str_starts_with($src, 'file://')) {
            return $src;
        }

        if (preg_match('~^https?://~i', $src) === 1) {
            $srcPath = (string) (parse_url($src, PHP_URL_PATH) ?? '');
            if ($srcPath !== '') {
                $candidate = $this->resolvePublicOrStoragePath($srcPath);
                if ($candidate !== null) {
                    return $this->toDataUriFromFile($candidate) ?? $this->toFileUri($candidate);
                }
            }

            return $src;
        }

        if (str_starts_with($src, '/')) {
            $candidate = $this->resolvePublicOrStoragePath($src);

            return $candidate !== null ? ($this->toDataUriFromFile($candidate) ?? $this->toFileUri($candidate)) : $src;
        }

        if (preg_match('~^[a-z][a-z0-9+\-.]*:~i', $src) === 1) {
            return $src;
        }

        $candidate = $this->resolvePublicOrStoragePath('/'.ltrim($src, '/'));

        return $candidate !== null ? ($this->toDataUriFromFile($candidate) ?? $this->toFileUri($candidate)) : $src;
    }

    private function resolvePublicOrStoragePath(string $publicPath): ?string
    {
        $relative = ltrim($publicPath, '/');
        $publicCandidate = public_path($relative);
        if (is_file($publicCandidate)) {
            return $publicCandidate;
        }

        if (str_starts_with($relative, 'storage/')) {
            $storageRelative = substr($relative, strlen('storage/'));
            $storageCandidate = storage_path('app/public/'.$storageRelative);
            if (is_file($storageCandidate)) {
                return $storageCandidate;
            }
        }

        return null;
    }

    private function toDataUriFromFile(string $absolutePath): ?string
    {
        try {
            if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
                return null;
            }

            $binary = file_get_contents($absolutePath);
            if (! is_string($binary) || $binary === '') {
                return null;
            }

            $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => 'image/png',
            };

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFileUri(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.$normalized;
        }

        return 'file://'.$normalized;
    }

    /**
     * @param array<string, array<int|string, mixed>> $datasets
     */
    private function appendChartsForTemplate(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, PrintTemplate $template, array $datasets, int $startRow): void
    {
        if ((string) $template->code !== 'sales_receiving_delivery_time_analysis') {
            return;
        }

        $received = $this->firstDatasetRow($datasets, 'received');
        $delivered = $this->firstDatasetRow($datasets, 'delivered');

        if ($received === [] && $delivered === []) {
            return;
        }

        $colors = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'];

        $receivedValues = [
            (float) ($received['slot_0900_1159'] ?? 0),
            (float) ($received['slot_1200_1400'] ?? 0),
            (float) ($received['slot_1401_1759'] ?? 0),
            (float) ($received['slot_1800_2000'] ?? 0),
            (float) ($received['slot_other'] ?? 0),
        ];
        $deliveredValues = [
            (float) ($delivered['slot_0900_1159'] ?? 0),
            (float) ($delivered['slot_1200_1400'] ?? 0),
            (float) ($delivered['slot_1401_1759'] ?? 0),
            (float) ($delivered['slot_1800_2000'] ?? 0),
            (float) ($delivered['slot_other'] ?? 0),
        ];

        $sheet->setCellValue('A'.(string) $startRow, 'Оформлення замовлення');
        $sheet->setCellValue('H'.(string) $startRow, 'Доставка замовлення');

        $receivedResource = $this->buildDonutChartImageResource(
            $receivedValues,
            $colors,
            (string) ($received['total_orders'] ?? '0')
        );
        $deliveredResource = $this->buildDonutChartImageResource(
            $deliveredValues,
            $colors,
            (string) ($delivered['total_orders'] ?? '0')
        );

        if ($receivedResource) {
            $drawing = new MemoryDrawing();
            $drawing->setName('Received Chart');
            $drawing->setDescription('Оформлення замовлення');
            $drawing->setImageResource($receivedResource);
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
            $drawing->setHeight(220);
            $drawing->setCoordinates('A'.(string) ($startRow + 1));
            $drawing->setWorksheet($sheet);
        }

        if ($deliveredResource) {
            $drawing = new MemoryDrawing();
            $drawing->setName('Delivered Chart');
            $drawing->setDescription('Доставка замовлення');
            $drawing->setImageResource($deliveredResource);
            $drawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
            $drawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
            $drawing->setHeight(220);
            $drawing->setCoordinates('H'.(string) ($startRow + 1));
            $drawing->setWorksheet($sheet);
        }
    }

    /**
     * @param array<string, array<int|string, mixed>> $datasets
     * @return array<string, mixed>
     */
    private function firstDatasetRow(array $datasets, string $key): array
    {
        $rows = $datasets[$key] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [];
        }

        $first = reset($rows);

        return is_array($first) ? $first : [];
    }

    /**
     * @param array<int, float> $values
     * @param array<int, string> $colors
     */
    private function buildDonutChartImageResource(array $values, array $colors, string $centerText): mixed
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $numbers = [];
        foreach ($values as $value) {
            $num = (float) $value;
            $numbers[] = $num > 0 ? $num : 0.0;
        }

        if ($numbers === []) {
            return null;
        }

        $palette = [];
        foreach ($colors as $color) {
            $hex = trim((string) $color);
            if ($hex !== '') {
                $palette[] = $hex;
            }
        }

        if ($palette === []) {
            $palette = ['#4f81bd', '#c0504d', '#9bbb59', '#8064a2', '#4bacc6'];
        }

        $size = 220;
        $img = imagecreatetruecolor($size, $size);
        if (! $img) {
            return null;
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
        imagefill($img, 0, 0, $transparent);
        imagealphablending($img, true);

        $cx = (int) ($size / 2);
        $cy = (int) ($size / 2);
        $diameter = 140;
        $sum = array_sum($numbers);
        if ($sum <= 0) {
            $sum = 1.0;
        }

        $start = -90.0;
        $segments = [];
        foreach ($numbers as $index => $value) {
            if ($value <= 0) {
                continue;
            }

            $angle = 360.0 * ($value / $sum);
            $end = $start + $angle;
            [$r, $g, $b] = $this->hexToRgb($palette[$index % count($palette)]);
            $color = imagecolorallocate($img, $r, $g, $b);

            imagefilledarc(
                $img,
                $cx,
                $cy,
                $diameter,
                $diameter,
                (int) round($start),
                (int) round($end),
                $color,
                IMG_ARC_PIE
            );

            $segments[] = [
                'start' => $start,
                'end' => $end,
                'percent' => ($value / $sum) * 100,
            ];

            $start = $end;
        }

        $hole = imagecolorallocate($img, 255, 255, 255);
        imagefilledellipse($img, $cx, $cy, 76, 76, $hole);
        $stroke = imagecolorallocate($img, 226, 232, 240);
        imageellipse($img, $cx, $cy, 76, 76, $stroke);

        $text = trim($centerText);
        if ($text !== '') {
            $textColor = imagecolorallocate($img, 51, 65, 85);
            $font = 3;
            $textWidth = imagefontwidth($font) * strlen($text);
            $textHeight = imagefontheight($font);
            imagestring($img, $font, (int) round($cx - $textWidth / 2), (int) round($cy - $textHeight / 2), $text, $textColor);
        }

        $labelTextColor = imagecolorallocate($img, 31, 41, 55);
        $labelFill = imagecolorallocate($img, 255, 255, 255);
        $labelBorder = imagecolorallocate($img, 203, 213, 225);
        $labelFont = 3;
        $labelRadius = 70.0;

        foreach ($segments as $segment) {
            $percent = (float) ($segment['percent'] ?? 0);
            if ($percent < 4.0) {
                continue;
            }

            $mid = (((float) $segment['start']) + ((float) $segment['end'])) / 2.0;
            $rad = deg2rad($mid);
            $x = (int) round($cx + cos($rad) * $labelRadius);
            $y = (int) round($cy + sin($rad) * $labelRadius);

            $label = (string) round($percent).'%';
            $labelWidth = imagefontwidth($labelFont) * strlen($label);
            $labelHeight = imagefontheight($labelFont);
            $padX = 4;
            $padY = 2;
            $left = $x - (int) round($labelWidth / 2) - $padX;
            $top = $y - (int) round($labelHeight / 2) - $padY;
            $right = $left + $labelWidth + ($padX * 2);
            $bottom = $top + $labelHeight + ($padY * 2);

            imagefilledrectangle($img, $left, $top, $right, $bottom, $labelFill);
            imagerectangle($img, $left, $top, $right, $bottom, $labelBorder);
            imagestring($img, $labelFont, $left + $padX, $top + $padY, $label, $labelTextColor);
        }

        return $img;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [79, 129, 189];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
