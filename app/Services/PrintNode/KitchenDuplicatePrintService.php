<?php

namespace App\Services\PrintNode;

use App\Enums\PrintOperationCode;
use App\Enums\OrderStatus;
use App\Models\PrintOperationProfile;
use App\Models\Setting;
use App\Models\Shop\ProductCharacteristicValue;
use App\Models\Shop\Order;
use App\Models\Shop\OrderItem;
use App\Services\Printing\PrintOperationService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KitchenDuplicatePrintService
{
    public function __construct(
        private readonly PrintNodeService $printNode,
        private readonly PrintOperationService $printOperationService,
    ) {
    }

    public function printForKitchenStatus(Order $order, ?string $operatorName = null): void
    {
        $status = $order->status instanceof OrderStatus ? $order->status : OrderStatus::tryFrom((string) $order->status);
        if ($status !== OrderStatus::Processing) {
            return;
        }

        $this->sendKitchenPrint($order, $operatorName, 'auto');
    }

    /**
     * @return array<string, mixed>
     */
    public function sendOperationReceipt(
        Order $order,
        string $operationCode,
        ?string $operatorName = null,
        int $copies = 1
    ): array {
        if (! $this->printNode->isEnabled()) {
            throw new \RuntimeException('PrintService вимкнений або не налаштований (api_base_url / site api key).');
        }

        if (! $this->printOperationService->hasActiveProfile($operationCode)) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$operationCode);
        }

        $vars = $this->buildKitchenTemplateVars($order, $operatorName, false, (int) ($order->kitchen_print_count ?? 0));
        $context = $this->buildKitchenOperationContext($vars);
        $context['client_name'] = trim((string) ($order->clients?->name ?? ''));

        $result = $this->printOperationService->print(
            $operationCode,
            params: [],
            context: $context,
            copiesOverride: max(1, $copies),
        );

        Log::info('Operation receipt sent', [
            'operation_code' => $operationCode,
            'order_id' => $order->id,
            'printjob_id' => $result['printjob_id'] ?? null,
            'printjob_ids' => $result['printjob_ids'] ?? [],
            'copies' => $result['copies'] ?? $copies,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function sendCombinedOperationReceipts(
        Order $order,
        string $primaryOperationCode,
        string $secondaryOperationCode,
        ?string $operatorName = null,
        int $copies = 1
    ): array {
        if (! $this->printNode->isEnabled()) {
            throw new \RuntimeException('PrintService вимкнений або не налаштований (api_base_url / site api key).');
        }

        if (! $this->printOperationService->hasActiveProfile($primaryOperationCode)) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$primaryOperationCode);
        }

        if (! $this->printOperationService->hasActiveProfile($secondaryOperationCode)) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$secondaryOperationCode);
        }

        $vars = $this->buildKitchenTemplateVars($order, $operatorName, false, (int) ($order->kitchen_print_count ?? 0));
        $context = $this->buildKitchenOperationContext($vars);
        $context['client_name'] = trim((string) ($order->clients?->name ?? ''));
        $normalizedCopies = max(1, $copies);

        $primaryResult = $this->printOperationService->print(
            $primaryOperationCode,
            params: [],
            context: $context,
            copiesOverride: $normalizedCopies,
        );

        $printerSelector = trim((string) ($primaryResult['printer_selector'] ?? ''));

        $secondaryResult = $this->printOperationService->print(
            $secondaryOperationCode,
            params: [],
            context: $context,
            copiesOverride: $normalizedCopies,
            printerSelectorOverride: $printerSelector !== '' ? $printerSelector : null,
        );

        Log::info('Combined operation receipts sent', [
            'order_id' => $order->id,
            'primary_operation_code' => $primaryOperationCode,
            'secondary_operation_code' => $secondaryOperationCode,
            'printer_selector' => $secondaryResult['printer_selector'] ?? $primaryResult['printer_selector'] ?? null,
            'primary_printjob_id' => $primaryResult['printjob_id'] ?? null,
            'primary_printjob_ids' => $primaryResult['printjob_ids'] ?? [],
            'secondary_printjob_id' => $secondaryResult['printjob_id'] ?? null,
            'secondary_printjob_ids' => $secondaryResult['printjob_ids'] ?? [],
            'copies' => $normalizedCopies,
        ]);

        return [
            'client_printjob_id' => $primaryResult['printjob_id'] ?? null,
            'client_printjob_ids' => $primaryResult['printjob_ids'] ?? [],
            'logistic_printjob_id' => $secondaryResult['printjob_id'] ?? null,
            'logistic_printjob_ids' => $secondaryResult['printjob_ids'] ?? [],
            'printer_selector' => $secondaryResult['printer_selector'] ?? $primaryResult['printer_selector'] ?? null,
            'copies' => $normalizedCopies,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendKitchenPrint(
        Order $order,
        ?string $operatorName = null,
        string $context = 'manual',
        ?int $copiesOverride = null
    ): array
    {
        if (! $this->printNode->isEnabled()) {
            throw new \RuntimeException('PrintService вимкнений або не налаштований (api_base_url / tenant_code).');
        }

        if ($context === 'auto' && ! (bool) Setting::admin('printservice.trigger_on_processing', Setting::admin('printnode.trigger_on_processing', true))) {
            return [
                'printed' => false,
                'reason' => 'trigger_disabled',
                'count' => (int) ($order->kitchen_print_count ?? 0),
            ];
        }

        $configuredId = (int) Setting::admin('printservice.printer_id', Setting::admin('printnode.printer_id', 0));
        $configuredName = trim((string) Setting::admin('printservice.printer_selector', Setting::admin('printnode.printer_name', '')));
        $printerSelector = $this->printNode->resolvePrinterSelector($configuredId > 0 ? $configuredId : null, $configuredName);

        if (! $printerSelector) {
            throw new \RuntimeException('Не вдалося знайти принтер. Перевірте printer_selector або printer_id.');
        }

        $copies = $copiesOverride !== null ? max(1, (int) $copiesOverride) : 1;
        $currentCount = (int) ($order->kitchen_print_count ?? 0);
        $nextCount = $currentCount + 1;
        $isDuplicate = $nextCount > 1;

        $title = ($isDuplicate ? 'Kitchen duplicate #' : 'Kitchen order #') . ($order->number ?: $order->id);
        $vars = $this->buildKitchenTemplateVars($order, $operatorName, $isDuplicate, $nextCount);
        $textPayload = $this->applyTemplate($vars);

        if ($this->printOperationService->hasActiveProfile(PrintOperationCode::KitchenWorkReceipt->value)) {
            $result = $this->sendKitchenProfileRawTextPrint(
                $order,
                $title,
                $textPayload,
                $isDuplicate,
                $nextCount,
                $context,
                $copies,
            );

            $order->kitchen_print_count = $nextCount;
            $order->kitchen_last_printed_at = now();
            $order->saveQuietly();

            Log::info('Kitchen duplicate print sent via operation profile', [
                'order_id' => $order->id,
                'printer_selector' => $result['printer_selector'] ?? null,
                'copies' => $result['copies'] ?? ($copiesOverride !== null ? $copies : 1),
                'printjob_id' => $result['printjob_id'] ?? null,
                'duplicate' => $isDuplicate,
                'count' => $nextCount,
                'context' => $context,
            ]);

            return [
                'printed' => true,
                'printjob_id' => $result['printjob_id'] ?? null,
                'printjob_ids' => $result['printjob_ids'] ?? [],
                'duplicate' => $isDuplicate,
                'count' => $nextCount,
            ];
        }

        $contentType = mb_strtolower(trim((string) Setting::admin('printservice.content_type', Setting::admin('printnode.content_type', 'auto'))));
        if (! in_array($contentType, ['auto', 'raw_base64', 'pdf_base64'], true)) {
            $contentType = 'auto';
        }

        $printerName = mb_strtolower($printerSelector);

        $usePdf = $contentType === 'pdf_base64'
            || ($contentType === 'auto' && str_contains($printerName, 'pdf'));

        if ($usePdf) {
            $pdfBinary = $this->buildSimplePdfFromText($textPayload);
            $jobIds = [];

            for ($copyIndex = 1; $copyIndex <= $copies; $copyIndex++) {
                $result = $this->printNode->createPdfBase64PrintJob(
                    printerSelector: $printerSelector,
                    title: $title,
                    pdfBinary: $pdfBinary,
                    qty: 1,
                );

                $jobId = (string) ($result['printjob_id'] ?? '');
                if ($jobId !== '') {
                    $jobIds[] = $jobId;
                }
            }
        } else {
            $encoding = mb_strtolower(trim((string) Setting::admin('printservice.raw_encoding', Setting::admin('printnode.raw_encoding', 'utf-8'))));
            $textPayload = $this->applyEncoding($textPayload, $encoding);

            $jobIds = [];

            for ($copyIndex = 1; $copyIndex <= $copies; $copyIndex++) {
                $result = $this->printNode->createRawPrintJob(
                    printerSelector: $printerSelector,
                    title: $title,
                    rawContent: $textPayload,
                    qty: 1,
                );

                $jobId = (string) ($result['printjob_id'] ?? '');
                if ($jobId !== '') {
                    $jobIds[] = $jobId;
                }
            }
        }

        $order->kitchen_print_count = $nextCount;
        $order->kitchen_last_printed_at = now();
        $order->saveQuietly();

        Log::info('Kitchen duplicate print sent', [
            'order_id' => $order->id,
            'printer_selector' => $printerSelector,
            'copies' => $copies,
            'printjob_id' => $jobIds !== [] ? end($jobIds) : null,
            'printjob_ids' => $jobIds,
            'duplicate' => $isDuplicate,
            'count' => $nextCount,
            'context' => $context,
        ]);

        return [
            'printed' => true,
            'printjob_id' => $jobIds !== [] ? end($jobIds) : null,
            'printjob_ids' => $jobIds,
            'duplicate' => $isDuplicate,
            'count' => $nextCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sendKitchenProfileRawTextPrint(
        Order $order,
        string $title,
        string $textPayload,
        bool $isDuplicate,
        int $nextCount,
        string $context,
        int $copies
    ): array {
        $profile = PrintOperationProfile::query()
            ->where('operation_code', PrintOperationCode::KitchenWorkReceipt->value)
            ->where('is_active', true)
            ->first();

        if (! $profile) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.PrintOperationCode::KitchenWorkReceipt->value);
        }

        $printerSelector = $this->printNode->resolvePrinterSelector(
            $profile->printer_id ? (int) $profile->printer_id : null,
            $profile->printer_name ?: null,
        );

        if (! $printerSelector) {
            throw new \RuntimeException('Не вдалося знайти принтер для профілю друку кухні.');
        }

        $encoding = mb_strtolower(trim((string) Setting::admin('printservice.raw_encoding', Setting::admin('printnode.raw_encoding', 'utf-8'))));
        $textPayload = $this->applyEncoding($textPayload, $encoding);
        $jobIds = [];

        for ($copyIndex = 1; $copyIndex <= $copies; $copyIndex++) {
            $result = $this->printNode->createRawPrintJob(
                printerSelector: $printerSelector,
                title: $title,
                rawContent: $textPayload,
                qty: 1,
            );

            $jobId = (string) ($result['printjob_id'] ?? '');
            if ($jobId !== '') {
                $jobIds[] = $jobId;
            }
        }

        Log::info('Kitchen duplicate print sent via operation profile raw mode', [
            'order_id' => $order->id,
            'printer_selector' => $printerSelector,
            'copies' => $copies,
            'printjob_id' => $jobIds !== [] ? end($jobIds) : null,
            'printjob_ids' => $jobIds,
            'duplicate' => $isDuplicate,
            'count' => $nextCount,
            'context' => $context,
        ]);

        return [
            'printjob_id' => $jobIds !== [] ? end($jobIds) : null,
            'printjob_ids' => $jobIds,
            'printer_selector' => $printerSelector,
            'copies' => $copies,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildKitchenPreview(Order $order, ?string $operatorName = null): array
    {
        $currentCount = (int) ($order->kitchen_print_count ?? 0);
        $nextCount = $currentCount + 1;
        $isDuplicate = $nextCount > 1;
        $vars = $this->buildKitchenTemplateVars($order, $operatorName, $isDuplicate, $nextCount);
        $text = $this->applyTemplate($vars);

        if ($this->printOperationService->hasActiveProfile(PrintOperationCode::KitchenWorkReceipt->value)) {
            $preview = $this->printOperationService->buildPreview(
                PrintOperationCode::KitchenWorkReceipt->value,
                params: [],
                context: $this->buildKitchenOperationContext($vars),
            );

            return [
                'text' => strip_tags((string) ($preview['html'] ?? '')),
                'preview_html' => (string) ($preview['preview_html'] ?? ''),
                'count' => $nextCount,
                'duplicate' => $isDuplicate,
                'copies' => (int) ($preview['copies'] ?? 1),
            ];
        }

        return [
            'text' => $text,
            'preview_html' => $this->buildReceiptPreviewHtml($text),
            'count' => $nextCount,
            'duplicate' => $isDuplicate,
            'copies' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOperationPreview(Order $order, string $operationCode, ?string $operatorName = null): array
    {
        if (! $this->printOperationService->hasActiveProfile($operationCode)) {
            throw new \RuntimeException('Немає активного профілю друку для операції: '.$operationCode);
        }

        $vars = $this->buildKitchenTemplateVars($order, $operatorName, false, (int) ($order->kitchen_print_count ?? 0));
        $context = $this->buildKitchenOperationContext($vars);
        $context['client_name'] = trim((string) ($order->clients?->name ?? ''));

        return $this->printOperationService->buildPreview(
            $operationCode,
            params: [],
            context: $context,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCombinedOperationPreview(
        Order $order,
        string $primaryOperationCode,
        string $secondaryOperationCode,
        ?string $operatorName = null
    ): array {
        $primaryPreview = $this->buildOperationPreview($order, $primaryOperationCode, $operatorName);
        $secondaryPreview = $this->buildOperationPreview($order, $secondaryOperationCode, $operatorName);

        $primaryHtml = trim((string) ($primaryPreview['preview_html'] ?? ''));
        $secondaryHtml = trim((string) ($secondaryPreview['preview_html'] ?? ''));

        $combinedHtml = '<div class="combined-receipt-preview">'
            .'<style>'
            .'.combined-receipt-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;align-items:start;}'
            .'.combined-receipt-preview-col{display:flex;flex-direction:column;gap:8px;min-width:0;}'
            .'.combined-receipt-preview-title{font-size:12px;font-weight:700;color:#334155;letter-spacing:.02em;text-transform:uppercase;}'
            .'@media (max-width: 560px){.combined-receipt-preview-grid{grid-template-columns:1fr;}}'
            .'</style>'
            .'<div class="combined-receipt-preview-grid">'
            .'<div class="combined-receipt-preview-col"><div class="combined-receipt-preview-title">Клиентский чек</div>'.$primaryHtml.'</div>'
            .'<div class="combined-receipt-preview-col"><div class="combined-receipt-preview-title">Чек для логиста</div>'.$secondaryHtml.'</div>'
            .'</div>'
            .'</div>';

        return [
            'preview_html' => $combinedHtml,
            'copies' => max(
                1,
                (int) ($primaryPreview['copies'] ?? 1),
                (int) ($secondaryPreview['copies'] ?? 1),
            ),
            'primary_preview_html' => $primaryHtml,
            'secondary_preview_html' => $secondaryHtml,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTestPreview(string $templateType = 'kitchen'): array
    {
        $payload = $this->buildTestReceiptPayload($templateType);

        return [
            'template_type' => $payload['template_type'],
            'text' => $payload['text'],
            'preview_html' => $this->buildReceiptPreviewFromBodyHtml($payload['body_html']),
            'copies' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendTestReceipt(string $templateType = 'kitchen', int $copies = 1): array
    {
        if (! $this->printNode->isEnabled()) {
            throw new \RuntimeException('PrintService вимкнений або не налаштований (api_base_url / tenant_code).');
        }

        $configuredId = (int) Setting::admin('printservice.printer_id', Setting::admin('printnode.printer_id', 0));
        $configuredName = trim((string) Setting::admin('printservice.printer_selector', Setting::admin('printnode.printer_name', '')));
        $printerSelector = $this->printNode->resolvePrinterSelector($configuredId > 0 ? $configuredId : null, $configuredName);

        if (! $printerSelector) {
            throw new \RuntimeException('Не вдалося знайти принтер. Перевірте printer_selector або printer_id.');
        }

        $copies = max(1, $copies);
        $payload = $this->buildTestReceiptPayload($templateType);
        $templateType = $payload['template_type'];

        $title = match ($templateType) {
            'client' => 'Client receipt TEST',
            'courier' => 'Courier service receipt TEST',
            default => 'Kitchen duplicate TEST',
        };

        $pdfBinary = $this->buildSimplePdfFromBodyHtml($payload['body_html']);
        $jobIds = [];

        for ($copyIndex = 1; $copyIndex <= $copies; $copyIndex++) {
            $result = $this->printNode->createPdfBase64PrintJob(
                printerSelector: $printerSelector,
                title: $title,
                pdfBinary: $pdfBinary,
                qty: 1,
            );

            $jobId = (string) ($result['printjob_id'] ?? '');
            if ($jobId !== '') {
                $jobIds[] = $jobId;
            }
        }

        return [
            'printed' => true,
            'printjob_id' => $jobIds !== [] ? end($jobIds) : null,
            'printjob_ids' => $jobIds,
            'template_type' => $templateType,
            'copies' => $copies,
            'use_pdf' => true,
        ];
    }

    public function printTestReceipt(): void
    {
        $this->sendTestReceipt('kitchen', 1);
    }

    private function buildReceiptText(Order $order, ?string $operatorName = null, bool $isDuplicate = false, int $printCount = 1): string
    {
        return $this->applyTemplate($this->buildKitchenTemplateVars($order, $operatorName, $isDuplicate, $printCount));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildKitchenTemplateVars(Order $order, ?string $operatorName = null, bool $isDuplicate = false, int $printCount = 1): array
    {
        $order->loadMissing(['items.product', 'clients', 'clientAddress', 'source']);

        $operator = trim((string) ($operatorName ?: auth('admin')->user()?->name ?: 'System'));
        $phone = trim((string) ($order->clients?->phone ?? ''));
        $printAt = now()->format('d/m/Y H:i');
        $deliveryAt = $this->formatOrderDateTime($order->date_order, $order->time_order);
        $issuedAt = $this->formatOrderDateTime($order->dat, $order->time_start);
        $address = $this->resolveAddressLine($order);
        $kitchenNote = trim((string) ($order->kitchen_note ?? ''));

        $siteName = trim((string) ($order->source?->name ?? ''));
        if ($siteName === '') {
            $siteName = trim((string) Setting::value('site_name'));
        }
        if ($siteName === '') {
            $siteName = 'Три пирога';
        }

        $itemLines = [];
        $itemRows = [];

        foreach ($order->items as $item) {
            $title = $this->resolveItemTitle($item);
            $qty = rtrim(rtrim(number_format((float) $item->qty, 2, '.', ''), '0'), '.');
            $itemLines[] = $qty . ' x ' . $title;

            $row = [
                'title' => $title,
                'qty' => $qty,
                'modifiers' => [],
                'kitchen_note' => '',
            ];

            $modifiers = collect($item->modifiers ?? [])->map(fn ($mod) => is_object($mod) ? (array) $mod : $mod);
            foreach ($modifiers as $modifier) {
                $modName = trim((string) ($modifier['name'] ?? $modifier['title'] ?? ''));
                if ($modName !== '') {
                    $itemLines[] = '  + ' . $modName;
                    $row['modifiers'][] = $modName;
                }
            }

            $itemKitchenNote = trim((string) ($item->kitchen_note ?? ''));
            if ($itemKitchenNote !== '') {
                $itemLines[] = '  * ' . $itemKitchenNote;
                $row['kitchen_note'] = $itemKitchenNote;
            }

            $itemRows[] = $row;
        }

        return [
            'order_id' => (int) $order->id,
            'kitchen_header' => $isDuplicate ? 'Дубликат заказа на кухню' : 'Заказ на кухню',
            'order_number' => (string) ($order->number ?: $order->id),
            'site_name' => $siteName,
            'operator' => $operator,
            'printed_at' => $printAt,
            'phone' => $phone !== '' ? $phone : '-',
            'delivery_time' => $deliveryAt ?: '-',
            'issued_time' => $issuedAt ?: '-',
            'delivery_type' => $order->self_pickup ? 'Самовывоз' : 'Доставка',
            'note' => $kitchenNote,
            'address' => $address !== '' ? $address : '-',
            'items' => implode("\n", $itemLines),
            'items_rows' => $itemRows,
            'total' => number_format((float) ($order->total_price_sale ?? $order->total_price ?? 0), 2, '.', ' ') . ' грн',
            'print_count' => (string) $printCount,
        ];
    }

    /**
     * @param array<string, mixed> $vars
     * @return array<string, mixed>
     */
    private function buildKitchenOperationContext(array $vars): array
    {
        $rows = is_array($vars['items_rows'] ?? null) ? $vars['items_rows'] : [];
        $itemsHtml = '';

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemsHtml .= '<div><strong>'
                . e((string) ($row['qty'] ?? ''))
                . ' x </strong>'
                . e((string) ($row['title'] ?? ''))
                . '</div>';

            $mods = is_array($row['modifiers'] ?? null) ? $row['modifiers'] : [];
            foreach ($mods as $mod) {
                $itemsHtml .= '<div style="padding-left:3mm;">+ ' . e((string) $mod) . '</div>';
            }

            $kitchenNote = trim((string) ($row['kitchen_note'] ?? ''));
            if ($kitchenNote !== '') {
                $itemsHtml .= '<div style="padding-left:3mm;">* ' . e($kitchenNote) . '</div>';
            }
        }

        return [
            'order_id' => (int) ($vars['order_id'] ?? 0),
            'order' => [
                'id' => (int) ($vars['order_id'] ?? 0),
                'number' => (string) ($vars['order_number'] ?? ''),
            ],
            'kitchen_header' => (string) ($vars['kitchen_header'] ?? ''),
            'order_number' => (string) ($vars['order_number'] ?? ''),
            'site_name' => (string) ($vars['site_name'] ?? ''),
            'operator' => (string) ($vars['operator'] ?? ''),
            'printed_at' => (string) ($vars['printed_at'] ?? ''),
            'phone' => (string) ($vars['phone'] ?? ''),
            'delivery_time' => (string) ($vars['delivery_time'] ?? ''),
            'issued_time' => (string) ($vars['issued_time'] ?? ''),
            'delivery_type' => (string) ($vars['delivery_type'] ?? ''),
            'note' => (string) ($vars['note'] ?? ''),
            'address' => (string) ($vars['address'] ?? ''),
            'items' => (string) ($vars['items'] ?? ''),
            'items_html' => $itemsHtml,
            'items_rows' => $rows,
            'total' => (string) ($vars['total'] ?? ''),
            'print_count' => (string) ($vars['print_count'] ?? ''),
        ];
    }

    /**
     * @param array<string, string> $vars
     */
    private function applyTemplate(array $vars, ?string $templateOverride = null): string
    {
        $template = trim((string) ($templateOverride ?? ''));

        if ($template === '') {
            $template = trim((string) Setting::admin('printservice.receipt_template', Setting::admin('printnode.receipt_template', '')));
        }

        if ($template === '') {
            $template = $this->resolvePresetTemplate();
        }

        foreach ($vars as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        $template = preg_replace('/^\s*Примечание:\s*$/m', '', $template) ?? $template;
        $template = preg_replace('/\n{3,}/', "\n\n", $template) ?? $template;

        return rtrim($template) . "\n";
    }

    private function resolvePresetTemplate(): string
    {
        $key = (string) Setting::admin('printservice.template_key', Setting::admin('printnode.template_key', 'classic'));

        return match ($key) {
            'compact_58' => implode("\n", [
                '=== {{kitchen_header}} ===',
                'Заказ: {{order_number}}',
                'Печать: {{printed_at}}',
                'Тел: {{phone}}',
                'Дост: {{delivery_time}}',
                'Выд: {{issued_time}}',
                '------------------------',
                '{{items}}',
                '------------------------',
                'ИТОГО: {{total}}',
            ]),
            'compact_80' => implode("\n", [
                '================================',
                '      {{kitchen_header}}',
                '================================',
                'Заказ №: {{order_number}}',
                'Работник: {{operator}}',
                'Печать: {{printed_at}}',
                'Телефон: {{phone}}',
                'Доставка: {{delivery_time}}',
                'Выдача: {{issued_time}}',
                'Тип: {{delivery_type}}',
                'Адрес: {{address}}',
                '-------------------------------',
                '{{items}}',
                '-------------------------------',
                'Сумма: {{total}}',
                '================================',
            ]),
            default => implode("\n", [
                '========================================',
                '           {{kitchen_header}}',
                '========================================',
                'Заказ №: {{order_number}}',
                'Работник: {{operator}}',
                'Время печати: {{printed_at}}',
                'Контактный телефон: {{phone}}',
                'Время доставки: {{delivery_time}}',
                'Время выдачи: {{issued_time}}',
                'Тип: {{delivery_type}}',
                'Примечание: {{note}}',
                'Адрес: {{address}}',
                '----------------------------------------',
                '{{items}}',
                '----------------------------------------',
                'Сумма: {{total}}',
                '========================================',
            ]),
        };
    }

    private function resolveItemTitle(OrderItem $item): string
    {
        $snapshot = $item->product_snapshot;
        if (is_array($snapshot)) {
            $title = trim((string) ($snapshot['title'] ?? $snapshot['name'] ?? ''));
            if ($title !== '') {
                $size = trim((string) ($snapshot['weight'] ?? $snapshot['size'] ?? ''));

                return $this->appendSizeToTitle($title, $size);
            }
        }

        $product = $item->product;
        if ($product) {
            $title = trim((string) ($product->name ?? ''));

            if ($title === '') {
                $raw = $product->getRawOriginal('title');
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $locale = app()->getLocale();
                        $title = trim((string) (
                            $decoded[$locale]
                            ?? $decoded['uk']
                            ?? $decoded['ru']
                            ?? $decoded['en']
                            ?? (count($decoded) ? reset($decoded) : '')
                        ));
                    } else {
                        $title = trim($raw, " \t\n\r\0\x0B\"");
                    }
                }
            }

            if ($title !== '') {
                $size = $this->resolveItemSizeLabel($item);

                if ($size === '') {
                    $size = trim((string) ($item->sku ?? ''));
                }

                if ($size === '') {
                    $size = trim((string) ($product->sku ?? ''));
                }

                if ($size === '') {
                    $size = $this->extractSizeFromShortName($this->resolveProductShortName($product));
                }

                return $this->appendSizeToTitle($title, $size);
            }
        }

        return 'Product #' . ($item->product_id ?: $item->id);
    }

    private function appendSizeToTitle(string $title, ?string $size): string
    {
        $title = trim($title);
        $size = trim((string) $size);

        if ($size === '') {
            return $title;
        }

        if ($this->titleContainsSize($title, $size)) {
            return $title;
        }

        return $title . ' (' . $size . ')';
    }

    private function titleContainsSize(string $title, string $size): bool
    {
        $title = trim($title);
        $size = preg_quote(trim($size), '/');

        if ($title === '' || $size === '') {
            return false;
        }

        return (bool) preg_match('/(?:\(|\[)\s*' . $size . '\s*(?:\)|\])/u', $title);
    }

    private function extractSizeFromShortName(?string $shortName): string
    {
        $shortName = trim((string) $shortName);
        if ($shortName === '') {
            return '';
        }

        if (preg_match('/\[(.+?)\]/u', $shortName, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/\b(\d{2,3}(?:[.,]\d+)?)\b/u', $shortName, $matches)) {
            return str_replace(',', '.', trim((string) ($matches[1] ?? '')));
        }

        return '';
    }

    private function resolveProductShortName($product): string
    {
        $shortName = trim((string) ($product->short_name ?? ''));
        if ($shortName !== '') {
            return $shortName;
        }

        $raw = $product->getRawOriginal('short_name');
        if (! is_string($raw) || $raw === '') {
            return '';
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $locale = app()->getLocale();

            return trim((string) (
                $decoded[$locale]
                ?? $decoded['uk']
                ?? $decoded['ru']
                ?? $decoded['en']
                ?? (count($decoded) ? reset($decoded) : '')
            ));
        }

        return trim($raw, " \t\n\r\0\x0B\"");
    }

    private function resolveItemSizeLabel(OrderItem $item): string
    {
        $product = $item->product;
        if (! $product) {
            return '';
        }

        $productIds = array_values(array_unique(array_filter([
            (int) $product->id,
            (int) ($product->parent_id ?? 0),
        ])));

        if ($productIds === []) {
            return '';
        }

        $priority = ['rozmir-pirogiv', 'rozmiri-insi', 'vaga-grami', 'vaga-setiv', 'vaga'];

        $rows = ProductCharacteristicValue::query()
            ->with([
                'characteristic:id,slug',
                'characteristicValue:id,characteristic_id,value',
                'characteristicValue.characteristic:id,slug',
            ])
            ->whereIn('product_id', $productIds)
            ->get();

        foreach ($productIds as $pid) {
            $productRows = $rows->where('product_id', $pid)->values();

            foreach ($priority as $slug) {
                $match = $productRows->first(function (ProductCharacteristicValue $row) use ($slug): bool {
                    $rowSlug = $row->characteristic?->slug
                        ?? $row->characteristicValue?->characteristic?->slug;

                    return $rowSlug === $slug;
                });

                if (! $match) {
                    continue;
                }

                $value = trim((string) ($match->value_text ?? ''));
                if ($value !== '') {
                    return $value;
                }

                if ($match->value_number !== null) {
                    return (string) $match->value_number;
                }

                if ($match->characteristicValue) {
                    $label = trim((string) ($match->characteristicValue->label ?? ''));
                    if ($label !== '') {
                        return $label;
                    }
                }
            }
        }

        return '';
    }

    private function resolveAddressLine(Order $order): string
    {
        $address = (array) ($order->address ?? []);

        $parts = array_filter([
            trim((string) ($address['street'] ?? $order->clientAddress?->street ?? '')),
            trim((string) ($address['house'] ?? $order->clientAddress?->house ?? '')),
            trim((string) ($address['apartment'] ?? $order->clientAddress?->apartment ?? '')),
            trim((string) ($address['city'] ?? $order->clientAddress?->city ?? '')),
        ], fn ($value) => $value !== '');

        return implode(', ', $parts);
    }

    private function formatOrderDateTime($date, $time): string
    {
        $datePart = '';
        $timePart = '';

        if ($date instanceof \Carbon\CarbonInterface) {
            $datePart = $date->format('d/m/Y');
        } elseif (is_string($date) && trim($date) !== '') {
            $datePart = date('d/m/Y', strtotime($date));
        }

        if ($time instanceof \Carbon\CarbonInterface) {
            $timePart = $time->format('H:i');
        } elseif (is_string($time) && trim($time) !== '') {
            $timePart = substr(trim($time), 0, 5);
        }

        return trim($datePart . ' ' . $timePart);
    }

    private function applyEncoding(string $payload, string $encoding): string
    {
        if ($encoding === 'cp866' && function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'CP866//TRANSLIT', $payload);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $payload;
    }

    private function buildSimplePdfFromText(string $text, ?string $logoDataUri = null): string
    {
        $lines = $this->splitReceiptLines($text);
        $htmlLines = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                $htmlLines[] = '&nbsp;';
                continue;
            }
            $htmlLines[] = e($line);
        }

        $layout = $this->resolvePdfLayoutSettings(max(1, count($lines)));
        $widthMm = (float) $layout['width_mm'];
        $heightMm = (float) $layout['height_mm'];
        $fontSize = (float) $layout['font_size_pt'];
        $lineHeight = (float) $layout['line_height'];
        $marginTop = (float) $layout['margin_top_mm'];
        $marginRight = (float) $layout['margin_right_mm'];
        $marginBottom = (float) $layout['margin_bottom_mm'];
        $marginLeft = (float) $layout['margin_left_mm'];

        $html = '<!doctype html><html><head><meta charset="UTF-8"><style>'
            . 'body{font-family: DejaVu Sans, sans-serif; font-size:' . $fontSize . 'pt; line-height:' . $lineHeight . ';'
            . ' margin:' . $marginTop . 'mm ' . $marginRight . 'mm ' . $marginBottom . 'mm ' . $marginLeft . 'mm;}'
            . '.line{white-space:pre-wrap; word-break:break-word;}'
            . '</style></head><body>';

        if (is_string($logoDataUri) && $logoDataUri !== '') {
            $html .= '<div style="text-align:center;margin-bottom:3mm;"><img src="' . e($logoDataUri) . '" style="max-width:65%;max-height:18mm;"></div>';
        }

        foreach ($htmlLines as $line) {
            $html .= '<div class="line">' . $line . '</div>';
        }

        $html .= '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');

        $widthPt = $this->mmToPt($widthMm);
        $heightPt = $this->mmToPt($heightMm);
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->render();

        return $dompdf->output();
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 2.834645669;
    }

    /**
     * @return array<int, string>
     */
    private function splitReceiptLines(string $text): array
    {
        return preg_split('/\r\n|\r|\n/', $text) ?: [];
    }

    /**
     * @return array<string, float>
     */
    private function resolvePdfLayoutSettings(int $lineCount): array
    {
        $preset = (string) Setting::admin('printservice.pdf_paper_preset', Setting::admin('printnode.pdf_paper_preset', '80mm'));
        $defaultWidth = match ($preset) {
            '58mm' => 58.0,
            '72mm' => 72.0,
            default => 80.0,
        };
        $defaultHeight = $preset === '58mm' ? 150.0 : 180.0;

        $widthMm = (float) Setting::admin('printservice.pdf_page_width_mm', Setting::admin('printnode.pdf_page_width_mm', $defaultWidth));
        $configuredHeightMm = (float) Setting::admin('printservice.pdf_page_height_mm', Setting::admin('printnode.pdf_page_height_mm', $defaultHeight));
        $fontSize = (float) Setting::admin('printservice.pdf_font_size_pt', Setting::admin('printnode.pdf_font_size_pt', 10));
        $lineHeight = (float) Setting::admin('printservice.pdf_line_height', Setting::admin('printnode.pdf_line_height', 1.25));
        $marginTop = (float) Setting::admin('printservice.pdf_margin_top_mm', Setting::admin('printnode.pdf_margin_top_mm', 3));
        $marginRight = (float) Setting::admin('printservice.pdf_margin_right_mm', Setting::admin('printnode.pdf_margin_right_mm', 2));
        $marginBottom = (float) Setting::admin('printservice.pdf_margin_bottom_mm', Setting::admin('printnode.pdf_margin_bottom_mm', 3));
        $marginLeft = (float) Setting::admin('printservice.pdf_margin_left_mm', Setting::admin('printnode.pdf_margin_left_mm', 2));

        $widthMm = $widthMm > 20 ? $widthMm : $defaultWidth;
        $configuredHeightMm = $configuredHeightMm > 40 ? $configuredHeightMm : $defaultHeight;
        $fontSize = $fontSize > 6 ? $fontSize : 10;
        $lineHeight = $lineHeight > 0.8 ? $lineHeight : 1.25;
        $lineCount = max(1, $lineCount);

        $lineHeightMm = ($fontSize * 0.3528) * $lineHeight;
        $estimatedContentHeightMm = ($lineCount * $lineHeightMm) + 8;
        $estimatedTotalHeightMm = $marginTop + $marginBottom + $estimatedContentHeightMm;
        $heightMm = max($configuredHeightMm, $estimatedTotalHeightMm);

        return [
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'font_size_pt' => $fontSize,
            'line_height' => $lineHeight,
            'margin_top_mm' => $marginTop,
            'margin_right_mm' => $marginRight,
            'margin_bottom_mm' => $marginBottom,
            'margin_left_mm' => $marginLeft,
        ];
    }

    private function buildReceiptPreviewHtml(string $text, ?string $logoDataUri = null): string
    {
        $lines = $this->splitReceiptLines($text);
        $layout = $this->resolvePdfLayoutSettings(max(1, count($lines)));

        $widthMm = (float) $layout['width_mm'];
        $heightMm = (float) $layout['height_mm'];
        $fontSize = (float) $layout['font_size_pt'];
        $lineHeight = (float) $layout['line_height'];
        $marginTop = (float) $layout['margin_top_mm'];
        $marginRight = (float) $layout['margin_right_mm'];
        $marginBottom = (float) $layout['margin_bottom_mm'];
        $marginLeft = (float) $layout['margin_left_mm'];

        $contentLines = '';

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $contentLines .= '<div style="white-space:pre-wrap;word-break:break-word;">'
                . ($line === '' ? '&nbsp;' : e($line))
                . '</div>';
        }

        $logoHtml = '';
        if (is_string($logoDataUri) && $logoDataUri !== '') {
            $logoHtml = '<div style="text-align:center;margin-bottom:3mm;"><img src="' . e($logoDataUri) . '" style="max-width:65%;max-height:18mm;"></div>';
        }

        return '<div style="background:#eef2f7;border:1px solid #d6deeb;border-radius:10px;padding:12px;overflow:auto;">'
            . '<div style="font-size:11px;color:#475569;margin-bottom:8px;">'
            . 'Бумага: ' . number_format($widthMm, 0, '.', '') . ' мм, '
            . 'поля: ' . number_format($marginTop, 0, '.', '') . '/'
            . number_format($marginRight, 0, '.', '') . '/'
            . number_format($marginBottom, 0, '.', '') . '/'
            . number_format($marginLeft, 0, '.', '') . ' мм'
            . '</div>'
            . '<div style="width:' . $widthMm . 'mm;min-height:' . $heightMm . 'mm;margin:0 auto;background:#fff;'
            . 'border:1px solid #e2e8f0;box-shadow:0 10px 24px rgba(15,23,42,.12);">'
            . '<div style="padding:' . $marginTop . 'mm ' . $marginRight . 'mm ' . $marginBottom . 'mm ' . $marginLeft
            . 'mm;font-family:\'DejaVu Sans\',sans-serif;font-size:' . $fontSize . 'pt;line-height:' . $lineHeight . ';">'
            . $logoHtml
            . $contentLines
            . '</div></div></div>';
    }

    /**
     * @return array{template_type: string, text: string, body_html: string}
     */
    private function buildTestReceiptPayload(string $templateType): array
    {
        $templateType = in_array($templateType, ['kitchen', 'client', 'courier'], true) ? $templateType : 'kitchen';

        $orderNumber = 'TEST-' . now()->format('His');
        $vars = [
            'client_logo' => '',
            'kitchen_header' => 'Заказ на кухню',
            'order_number' => $orderNumber,
            'operator' => 'Test Operator',
            'printed_at' => now()->format('d/m/Y H:i'),
            'phone' => '0660000000',
            'client_name' => 'Тестовий клієнт',
            'delivery_time' => now()->addMinutes(45)->format('d/m/Y H:i'),
            'issued_time' => now()->format('d/m/Y H:i'),
            'delivery_type' => 'Доставка',
            'note' => 'Тестовий друк шаблону',
            'address' => 'м. Київ, вул. Тестова, 1',
            'items' => "1 x Пиріг яблучний\n1 x Пиріг вишневий\n  + Без цибулі",
            'total' => '499.00 грн',
            'print_count' => '1',
        ];

        if ($templateType === 'client') {
            $logoDataUri = $this->resolveClientLogoDataUri();
            $vars['client_logo'] = $logoDataUri
                ? '<div style="text-align:center;margin-bottom:3mm;"><img src="' . e($logoDataUri) . '" style="max-width:65%;max-height:18mm;"></div>'
                : '';
        }

        $template = $this->resolveTemplateByType($templateType);
        $rendered = $this->renderTemplate($template, $vars);

        $bodyHtml = $this->templateLooksLikeHtml($rendered)
            ? $rendered
            : $this->plainTextToReceiptHtml($rendered);

        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($rendered)) ?? '') !== ''
            ? trim(html_entity_decode(strip_tags($rendered), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            : trim($rendered);

        return [
            'template_type' => $templateType,
            'text' => $text,
            'body_html' => $bodyHtml,
        ];
    }

    private function resolveTemplateByType(string $templateType): string
    {
        if ($templateType === 'client') {
            return trim((string) Setting::admin('printservice.client_receipt_template', Setting::admin('printnode.client_receipt_template', ''))) ?: $this->resolveDefaultClientTemplate();
        }

        if ($templateType === 'courier') {
            return trim((string) Setting::admin('printservice.courier_receipt_template', Setting::admin('printnode.courier_receipt_template', ''))) ?: $this->resolveDefaultCourierTemplate();
        }

        return trim((string) Setting::admin('printservice.receipt_template', Setting::admin('printnode.receipt_template', ''))) ?: $this->resolvePresetTemplate();
    }

    private function resolveClientLogoDataUri(): ?string
    {
        $path = trim((string) Setting::admin('printservice.client_logo_path', Setting::admin('printnode.client_logo_path', '')));

        if ($path === '') {
            return null;
        }

        try {
            if (! Storage::disk('public')->exists($path)) {
                return null;
            }

            $binary = Storage::disk('public')->get($path);
            if ($binary === '') {
                return null;
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                default => 'image/png',
            };

            return 'data:' . $mime . ';base64,' . base64_encode($binary);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveDefaultClientTemplate(): string
    {
        return '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:1mm;">ЧЕК ДЛЯ КЛІЄНТА</div>'
            . '{{client_logo}}'
            . '<div><strong>Замовлення №:</strong> {{order_number}}</div>'
            . '<div><strong>Час друку:</strong> {{printed_at}}</div>'
            . '<div><strong>Телефон:</strong> {{phone}}</div>'
            . '<div><strong>Час доставки:</strong> {{delivery_time}}</div>'
            . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
            . '<div><strong>Адреса:</strong> {{address}}</div>'
            . '<hr>'
            . '<div>{{items}}</div>'
            . '<hr>'
            . '<div style="font-size:13pt;font-weight:700;">До сплати: {{total}}</div>'
            . '<div style="text-align:center;margin-top:2mm;">ДЯКУЄМО ЗА ЗАМОВЛЕННЯ</div>';
    }

    private function resolveDefaultCourierTemplate(): string
    {
        return '<div style="text-align:center;font-weight:700;font-size:14pt;margin-bottom:2mm;">СЛУЖБОВИЙ ЧЕК КУРʼЄРА</div>'
            . '<div><strong>Замовлення №:</strong> {{order_number}}</div>'
            . '<div><strong>Друк:</strong> {{printed_at}}</div>'
            . '<div><strong>Оператор:</strong> {{operator}}</div>'
            . '<div><strong>Клієнт:</strong> {{client_name}}</div>'
            . '<div><strong>Телефон:</strong> {{phone}}</div>'
            . '<div><strong>Час доставки:</strong> {{delivery_time}}</div>'
            . '<div><strong>Тип:</strong> {{delivery_type}}</div>'
            . '<div><strong>Адреса:</strong> {{address}}</div>'
            . '<div><strong>Коментар:</strong> {{note}}</div>'
            . '<hr>'
            . '<div>{{items}}</div>'
            . '<hr>'
            . '<div style="font-size:13pt;font-weight:700;">До сплати: {{total}}</div>';
    }

    /**
     * @param array<string, string> $vars
     */
    private function renderTemplate(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }

        return trim($template);
    }

    private function templateLooksLikeHtml(string $template): bool
    {
        return (bool) preg_match('/<[^>]+>/', $template);
    }

    private function plainTextToReceiptHtml(string $text): string
    {
        $lines = $this->splitReceiptLines($text);
        $html = '';

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $html .= '<div style="white-space:pre-wrap;word-break:break-word;">'
                . ($line === '' ? '&nbsp;' : e($line))
                . '</div>';
        }

        return $html;
    }

    private function buildReceiptPreviewFromBodyHtml(string $bodyHtml): string
    {
        $layout = $this->resolvePdfLayoutSettings(max(1, count($this->splitReceiptLines(strip_tags($bodyHtml)))));

        $widthMm = (float) $layout['width_mm'];
        $heightMm = (float) $layout['height_mm'];
        $marginTop = (float) $layout['margin_top_mm'];
        $marginRight = (float) $layout['margin_right_mm'];
        $marginBottom = (float) $layout['margin_bottom_mm'];
        $marginLeft = (float) $layout['margin_left_mm'];

        return '<div style="background:#eef2f7;border:1px solid #d6deeb;border-radius:10px;padding:12px;overflow:auto;">'
            . '<div style="font-size:11px;color:#475569;margin-bottom:8px;">'
            . 'Бумага: ' . number_format($widthMm, 0, '.', '') . ' мм, '
            . 'поля: ' . number_format($marginTop, 0, '.', '') . '/'
            . number_format($marginRight, 0, '.', '') . '/'
            . number_format($marginBottom, 0, '.', '') . '/'
            . number_format($marginLeft, 0, '.', '') . ' мм'
            . '</div>'
            . '<div style="width:' . $widthMm . 'mm;min-height:' . $heightMm . 'mm;margin:0 auto;background:#fff;'
            . 'border:1px solid #e2e8f0;box-shadow:0 10px 24px rgba(15,23,42,.12);">'
            . $this->buildReceiptBodyWrapperHtml($bodyHtml, $layout)
            . '</div></div>';
    }

    private function buildSimplePdfFromBodyHtml(string $bodyHtml): string
    {
        $layout = $this->resolvePdfLayoutSettings(max(1, count($this->splitReceiptLines(strip_tags($bodyHtml)))));
        $html = '<!doctype html><html><head><meta charset="UTF-8"></head><body>'
            . $this->buildReceiptBodyWrapperHtml($bodyHtml, $layout)
            . '</body></html>';

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');

        $widthPt = $this->mmToPt((float) $layout['width_mm']);
        $heightPt = $this->mmToPt((float) $layout['height_mm']);
        $dompdf->setPaper([0, 0, $widthPt, $heightPt]);
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param array<string, float> $layout
     */
    private function buildReceiptBodyWrapperHtml(string $bodyHtml, array $layout): string
    {
        $fontSize = (float) $layout['font_size_pt'];
        $lineHeight = (float) $layout['line_height'];
        $marginTop = (float) $layout['margin_top_mm'];
        $marginRight = (float) $layout['margin_right_mm'];
        $marginBottom = (float) $layout['margin_bottom_mm'];
        $marginLeft = (float) $layout['margin_left_mm'];

        return '<div style="padding:' . $marginTop . 'mm ' . $marginRight . 'mm ' . $marginBottom . 'mm ' . $marginLeft . 'mm;'
            . 'font-family:\'DejaVu Sans\',sans-serif;font-size:' . $fontSize . 'pt;line-height:' . $lineHeight . ';">'
            . $bodyHtml
            . '</div>';
    }
}
