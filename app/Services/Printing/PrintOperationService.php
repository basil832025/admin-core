<?php

namespace App\Services\Printing;

use App\Models\PrintOperationProfile;
use App\Models\PrintTemplate;
use App\Models\Setting;
use App\Services\PrintNode\PrintNodeService;
use Dompdf\Dompdf;
use Dompdf\Options;

class PrintOperationService
{
    public function __construct(
        private readonly PrintNodeService $printNode,
        private readonly TwigTemplateRenderService $renderer,
        private readonly TemplateParameterResolver $parameterResolver,
    ) {}

    public function hasActiveProfile(string $operationCode): bool
    {
        return $this->findActiveProfile($operationCode) !== null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function buildPreview(string $operationCode, array $params = [], array $context = []): array
    {
        $profile = $this->findActiveProfile($operationCode);
        if (! $profile || ! $profile->template || ! $profile->template->is_active) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$operationCode);
        }

        $resolved = $this->parameterResolver->resolve($profile->template, $profile, $params, $context);
        if ($resolved['missing_required'] !== []) {
            throw new \RuntimeException('Не переданы обязательные параметры шаблона: '.implode(', ', $resolved['missing_required']));
        }

        $rendered = $this->renderer->renderTemplate($profile->template, $resolved['params'], $context);
        $layout = $this->resolveLayout($profile);

        return [
            'profile' => $profile,
            'html' => $rendered['html'],
            'preview_html' => $this->buildPreviewHtml($rendered['html'], $layout, $profile->template),
            'resolved_params' => $resolved['params'],
            'copies' => max(1, (int) $profile->copies),
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function print(string $operationCode, array $params = [], array $context = [], ?int $copiesOverride = null): array
    {
        if (! $this->printNode->isEnabled()) {
            throw new \RuntimeException('PrintService вимкнений або не налаштований (api_base_url / tenant_code).');
        }

        $profile = $this->findActiveProfile($operationCode);
        if (! $profile || ! $profile->template || ! $profile->template->is_active) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$operationCode);
        }

        $printerSelector = $this->resolvePrinterSelector($profile);
        if (! $printerSelector) {
            throw new \RuntimeException('Не вдалося знайти принтер для профілю друку.');
        }

        $resolved = $this->parameterResolver->resolve($profile->template, $profile, $params, $context);
        if ($resolved['missing_required'] !== []) {
            throw new \RuntimeException('Не переданы обязательные параметры шаблона: '.implode(', ', $resolved['missing_required']));
        }

        $rendered = $this->renderer->renderTemplate($profile->template, $resolved['params'], $context);
        $layout = $this->resolveLayout($profile);
        $pdfBinary = $this->buildPdfFromHtml($rendered['html'], $layout, $profile->template);
        $copies = $copiesOverride !== null ? max(1, (int) $copiesOverride) : max(1, (int) $profile->copies);

        $result = $this->printNode->createPdfBase64PrintJob(
            printerSelector: $printerSelector,
            title: $this->resolvePrintTitle($operationCode),
            pdfBinary: $pdfBinary,
            qty: $copies,
        );

        return [
            'printjob_id' => $result['printjob_id'] ?? null,
            'printer_selector' => $printerSelector,
            'copies' => $copies,
            'profile_id' => $profile->id,
            'template_id' => $profile->template->id,
            'resolved_params' => $resolved['params'],
        ];
    }

    private function findActiveProfile(string $operationCode): ?PrintOperationProfile
    {
        return PrintOperationProfile::query()
            ->with('template')
            ->where('operation_code', $operationCode)
            ->where('is_active', true)
            ->first();
    }

    private function resolvePrinterSelector(PrintOperationProfile $profile): ?string
    {
        $configuredId = (int) ($profile->printer_id ?: Setting::admin('printservice.printer_id', Setting::admin('printnode.printer_id', 0)));
        $configuredName = trim((string) ($profile->printer_name
            ?: Setting::admin('printservice.printer_selector', Setting::admin('printnode.printer_name', ''))));

        return $this->printNode->resolvePrinterSelector($configuredId > 0 ? $configuredId : null, $configuredName);
    }

    /**
     * @return array<string, float>
     */
    private function resolveLayout(PrintOperationProfile $profile): array
    {
        $paper = is_array($profile->paper_settings) ? $profile->paper_settings : [];

        [$templateDefaultWidth, $templateDefaultHeight] = $this->resolveTemplatePaperDefaults($profile);
        [$templateMarginTop, $templateMarginRight, $templateMarginBottom, $templateMarginLeft] = $this->resolveTemplateMarginDefaults($profile);

        $globalPreset = (string) Setting::admin('printservice.pdf_paper_preset', Setting::admin('printnode.pdf_paper_preset', '80mm'));
        [$globalDefaultWidth, $globalDefaultHeight] = $this->resolveGlobalPaperDefaults($globalPreset);

        $baseWidth = $templateDefaultWidth ?? (float) Setting::admin('printservice.pdf_page_width_mm', Setting::admin('printnode.pdf_page_width_mm', $globalDefaultWidth));
        $baseHeight = $templateDefaultHeight ?? (float) Setting::admin('printservice.pdf_page_height_mm', Setting::admin('printnode.pdf_page_height_mm', $globalDefaultHeight));

        $widthMm = (float) ($paper['width_mm'] ?? $baseWidth);
        $heightMm = (float) ($paper['height_mm'] ?? $baseHeight);
        $fontSize = (float) ($paper['font_size_pt'] ?? Setting::admin('printservice.pdf_font_size_pt', Setting::admin('printnode.pdf_font_size_pt', 10)));
        $lineHeight = (float) ($paper['line_height'] ?? Setting::admin('printservice.pdf_line_height', Setting::admin('printnode.pdf_line_height', 1.25)));
        $baseMarginTop = $templateMarginTop ?? (float) Setting::admin('printservice.pdf_margin_top_mm', Setting::admin('printnode.pdf_margin_top_mm', 3));
        $baseMarginRight = $templateMarginRight ?? (float) Setting::admin('printservice.pdf_margin_right_mm', Setting::admin('printnode.pdf_margin_right_mm', 2));
        $baseMarginBottom = $templateMarginBottom ?? (float) Setting::admin('printservice.pdf_margin_bottom_mm', Setting::admin('printnode.pdf_margin_bottom_mm', 3));
        $baseMarginLeft = $templateMarginLeft ?? (float) Setting::admin('printservice.pdf_margin_left_mm', Setting::admin('printnode.pdf_margin_left_mm', 2));

        $marginTop = (float) ($paper['margin_top_mm'] ?? $baseMarginTop);
        $marginRight = (float) ($paper['margin_right_mm'] ?? $baseMarginRight);
        $marginBottom = (float) ($paper['margin_bottom_mm'] ?? $baseMarginBottom);
        $marginLeft = (float) ($paper['margin_left_mm'] ?? $baseMarginLeft);

        return [
            'width_mm' => $widthMm > 20 ? $widthMm : $baseWidth,
            'height_mm' => $heightMm > 40 ? $heightMm : $baseHeight,
            'font_size_pt' => $fontSize > 6 ? $fontSize : 10,
            'line_height' => $lineHeight > 0.8 ? $lineHeight : 1.25,
            'margin_top_mm' => $marginTop,
            'margin_right_mm' => $marginRight,
            'margin_bottom_mm' => $marginBottom,
            'margin_left_mm' => $marginLeft,
        ];
    }

    /**
     * @param  array<string, float>  $layout
     */
    private function buildPreviewHtml(string $bodyHtml, array $layout, ?PrintTemplate $template = null): string
    {
        $widthMm = (float) $layout['width_mm'];
        $heightMm = (float) $layout['height_mm'];
        $marginTop = (float) $layout['margin_top_mm'];
        $marginRight = (float) $layout['margin_right_mm'];
        $marginBottom = (float) $layout['margin_bottom_mm'];
        $marginLeft = (float) $layout['margin_left_mm'];

        $bodyHtml = $this->injectCssIntoBodyHtml($bodyHtml, $this->resolveTemplateCss($template));

        return '<div style="background:#eef2f7;border:1px solid #d6deeb;border-radius:10px;padding:12px;overflow:auto;">'
            .'<div style="font-size:11px;color:#475569;margin-bottom:8px;">'
            .'Бумага: '.number_format($widthMm, 0, '.', '').' мм, '
            .'поля: '.number_format($marginTop, 0, '.', '').'/'
            .number_format($marginRight, 0, '.', '').'/'
            .number_format($marginBottom, 0, '.', '').'/'
            .number_format($marginLeft, 0, '.', '').' мм'
            .'</div>'
            .'<div style="width:'.$widthMm.'mm;min-height:'.$heightMm.'mm;margin:0 auto;background:#fff;'
            .'border:1px solid #e2e8f0;box-shadow:0 10px 24px rgba(15,23,42,.12);">'
            .$this->buildBodyWrapperHtml($bodyHtml, $layout)
            .'</div></div>';
    }

    /**
     * @param  array<string, float>  $layout
     */
    private function buildPdfFromHtml(string $bodyHtml, array $layout, ?PrintTemplate $template = null): string
    {
        $templateCss = $this->resolveTemplateCss($template);
        $bodyHtml = $this->normalizeHtmlForPdf($bodyHtml);

        $html = '<!doctype html><html><head><meta charset="UTF-8">'
            .($templateCss !== '' ? '<style>'.$templateCss.'</style>' : '')
            .'</head><body>'
            .$this->buildBodyWrapperHtml($bodyHtml, $layout)
            .'</body></html>';

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');

        $widthPt = $this->mmToPt((float) $layout['width_mm']);
        $heightPt = $this->mmToPt((float) $layout['height_mm']);
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function injectCssIntoBodyHtml(string $bodyHtml, string $css): string
    {
        if (trim($css) === '') {
            return $bodyHtml;
        }

        return '<style>'.$css.'</style>'.$bodyHtml;
    }

    private function resolveTemplateCss(?PrintTemplate $template): string
    {
        if (! $template) {
            return '';
        }

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
     * @param  array<string, float>  $layout
     */
    private function buildBodyWrapperHtml(string $bodyHtml, array $layout): string
    {
        return '<div style="padding:'
            .$layout['margin_top_mm'].'mm '
            .$layout['margin_right_mm'].'mm '
            .$layout['margin_bottom_mm'].'mm '
            .$layout['margin_left_mm'].'mm;'
            .'font-family:\'DejaVu Sans\',sans-serif;font-size:'
            .$layout['font_size_pt'].'pt;line-height:'
            .$layout['line_height'].';">'
            .$bodyHtml
            .'</div>';
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

            $appUrl = (string) config('app.url', '');
            $srcHost = parse_url($src, PHP_URL_HOST);
            $appHost = parse_url($appUrl, PHP_URL_HOST);

            if ($srcHost !== null && $appHost !== null && strcasecmp($srcHost, $appHost) === 0 && $srcPath !== '') {
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

    private function toFileUri(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        if (preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            return 'file:///'.$normalized;
        }

        return 'file://'.$normalized;
    }

    private function resolvePrintTitle(string $operationCode): string
    {
        return match ($operationCode) {
            'client_receipt' => 'Client receipt',
            'logistic_receipt' => 'Logistic receipt',
            default => 'Kitchen work receipt',
        };
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 2.834645669;
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function resolveTemplatePaperDefaults(PrintOperationProfile $profile): array
    {
        $template = $profile->template;
        if (! $template) {
            return [null, null];
        }

        $preset = (string) ($template->default_paper_preset ?? '');
        $customWidth = (float) ($template->default_paper_width_mm ?? 0);
        $customHeight = (float) ($template->default_paper_height_mm ?? 0);

        return match ($preset) {
            'a4' => [210.0, 297.0],
            'a5' => [148.0, 210.0],
            'thermal_80' => [80.0, 3650.0],
            'thermal_58' => [58.0, 3650.0],
            'custom' => [
                $customWidth > 20 ? $customWidth : 210.0,
                $customHeight > 20 ? $customHeight : 297.0,
            ],
            default => [null, null],
        };
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function resolveGlobalPaperDefaults(string $preset): array
    {
        return match ($preset) {
            '58mm' => [58.0, 3650.0],
            default => [80.0, 3650.0],
        };
    }

    /**
     * @return array{0: float|null, 1: float|null, 2: float|null, 3: float|null}
     */
    private function resolveTemplateMarginDefaults(PrintOperationProfile $profile): array
    {
        $template = $profile->template;
        if (! $template) {
            return [null, null, null, null];
        }

        return [
            $template->default_margin_top_mm !== null ? (float) $template->default_margin_top_mm : null,
            $template->default_margin_right_mm !== null ? (float) $template->default_margin_right_mm : null,
            $template->default_margin_bottom_mm !== null ? (float) $template->default_margin_bottom_mm : null,
            $template->default_margin_left_mm !== null ? (float) $template->default_margin_left_mm : null,
        ];
    }
}
