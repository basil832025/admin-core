<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasHistoryOrderActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasPromotionsActions;
use App\Enums\PaymentMethodEnum;
use App\Enums\PrintOperationCode;
use App\Models\Kitchen\KitchenTicket;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\OrderItem;
use App\Services\Callcenter\ExternalSyncService;
use App\Services\CashalotFiscalService;
use App\Services\OrderPricing;
use App\Services\OrderZoneSyncService;
use App\Services\PrintNode\KitchenDuplicatePrintService;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    use HasHistoryOrderActions;
    use HasMenuCatalogActions;
    use HasPromotionsActions;

    public ?string $pendingStatus = null;

    public ?string $prevStatus = null;

    private bool $zoneNeedsRefresh = false;

    protected static string $resource = OrderResource::class;

    public function getHeading(): string | Htmlable
    {
        $number = trim((string) ($this->record?->number ?? ''));
        $sourceName = trim((string) ($this->record?->source?->name ?? ''));

        if ($sourceName === '') {
            $sourceName = trim((string) (Setting::value('site_name') ?: 'Основний сайт'));
        }

        if ($number === '') {
            return parent::getHeading();
        }

        if ($sourceName === '') {
            return __('callcenter.pages.edit.heading', ['number' => $number]);
        }

        return new HtmlString(
            e(__('callcenter.pages.edit.heading', ['number' => $number])) . ' · <span style="color:#2563eb;font-weight:700;">' . e($sourceName) . '</span>'
        );
    }

    public function getTitle(): string | Htmlable
    {
        $number = trim((string) ($this->record?->number ?? ''));
        $sourceName = trim((string) ($this->record?->source?->name ?? ''));

        if ($sourceName === '') {
            $sourceName = trim((string) (Setting::value('site_name') ?: 'Основний сайт'));
        }

        if ($number === '') {
            return parent::getTitle();
        }

        return __('callcenter.pages.edit.heading', ['number' => $number]) . ($sourceName !== '' ? (' · ' . $sourceName) : '');
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->record?->exists) {
            return;
        }

        try {
            app(ExternalSyncService::class)->repairImportedOrderByLocalId((int) $this->record->id);
            $this->record->refresh();
        } catch (\Throwable) {
            // keep edit page usable even if self-heal fails
        }

        app(OrderZoneSyncService::class)->syncIfNeeded($this->record, false);
        $this->record->refresh();
    }

    /*   protected function getRedirectUrl(): string
       {
           return $this->getResource()::getUrl('index');
       }*/
    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()
                ->label(__('order.actions.cancel'))
                ->color('warning')
                ->url($this->getResource()::getUrl('index')),

            $this->openMenuCatalogAction(),

            $this->openPromotionsAction(),

            Action::make('cashalot_return')
                ->label('Отменить фискальный чек')
                ->icon('heroicon-o-receipt-refund')
                ->color('warning')
                ->visible(function (): bool {
                    $record = $this->record;
                    if (! $record) {
                        return false;
                    }

                    $user = auth('admin')->user();
                    $allowed = $user instanceof \App\Models\User
                        && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                            || $user->can('refund_payparts_payment'));

                    $hasSaleCashalot = $record->cashalotLogs()
                        ->where('status', 'success')
                        ->where(function ($query) {
                            $query->whereNull('payment_type')
                                ->orWhere('payment_type', '!=', 'Cashalot return');
                        })
                        ->exists();

                    $hasReturnCashalot = $record->cashalotLogs()
                        ->where('status', 'success')
                        ->where('payment_type', 'Cashalot return')
                        ->exists();

                    return $allowed && $hasSaleCashalot && ! $hasReturnCashalot;
                })
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Отмена фискального чека по заказу №' . ($this->record?->number ?: $this->record?->id))
                ->modalDescription(fn (): string => sprintf(
                    'Отменить фискальный чек на %.2f грн? После подтверждения будет сформирован сторно-чек Cashalot. Повторно запускать операцию нельзя, пока возврат не завершен.',
                    (float) ($this->record?->grand_total ?? 0)
                ))
                ->modalSubmitActionLabel('Отменить чек')
                ->action(function (): void {
                    $record = $this->record;
                    $user = auth('admin')->user();

                    try {
                        if (! $record) {
                            throw new \RuntimeException('Заказ не найден.');
                        }

                        $allowed = $user instanceof \App\Models\User
                            && ((method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
                                || $user->can('refund_payparts_payment'));

                        if (! $allowed) {
                            throw new \RuntimeException('Недостаточно прав для отмены фискального чека.');
                        }

                        $cashalotLog = $record->cashalotLogs()
                            ->where('status', 'success')
                            ->where(function ($query) {
                                $query->whereNull('payment_type')
                                    ->orWhere('payment_type', '!=', 'Cashalot return');
                            })
                            ->latest('id')
                            ->first();

                        if (! $cashalotLog) {
                            throw new \RuntimeException('Не найден успешный фискальный чек для сторно.');
                        }

                        $returnLog = app(CashalotFiscalService::class)
                            ->fiscalizeReturnCheck($record, $cashalotLog, $user?->id);

                        Notification::make()
                            ->title($returnLog?->status === 'success'
                                ? 'Фискальный чек отменен'
                                : 'Сторно чека принято и ожидает обработки')
                            ->{$returnLog?->status === 'success' ? 'success' : 'warning'}()
                            ->send();
                    } catch (\Throwable $e) {
                        Log::error('Cashalot return failed', [
                            'order_id' => $record?->id,
                            'user_id' => $user?->id,
                            'error' => $e->getMessage(),
                        ]);

                        Notification::make()
                            ->danger()
                            ->title('Отмена фискального чека не выполнена')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('print_kitchen')
                ->label(function (): string {
                    $count = (int) ($this->record?->kitchen_print_count ?? 0);
                    $base = __('callcenter.actions.print_kitchen');

                    if ($count > 1) {
                        return $base . ' (' . $count . ') ' . __('callcenter.actions.duplicate');
                    }

                    if ($count > 0) {
                        return $base.' ('.$count.')';
                    }

                    return $base;
                })
                ->icon('heroicon-o-printer')
                ->color(fn (): string => ((int) ($this->record?->kitchen_print_count ?? 0)) > 0 ? 'warning' : 'gray')
                ->extraAttributes([
                    'data-hotkey' => 'cc-print-kitchen',
                    'data-hotkey-label' => 'Alt+R',
                ])
                ->modalHeading(__('callcenter.print.kitchen.preview_heading'))
                ->modalDescription(__('callcenter.print.kitchen.preview_description'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->fillForm(function (): array {
                    if (! $this->record?->exists) {
                        return [
                            'copies' => 1,
                            'preview' => '',
                        ];
                    }

                    $preview = app(KitchenDuplicatePrintService::class)
                        ->buildKitchenPreview($this->record, auth('admin')->user()?->name);

                    return [
                        'copies' => (int) ($preview['copies'] ?? 1),
                        'preview' => (string) ($preview['text'] ?? ''),
                        'preview_html' => (string) ($preview['preview_html'] ?? ''),
                    ];
                })
                ->form([
                    Hidden::make('preview_html')
                        ->dehydrated(false),
                    Grid::make(12)
                        ->schema([
                            TextInput::make('copies')
                                ->label(__('callcenter.print.kitchen.copies'))
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->step(1)
                                ->required()
                                ->columnSpan(3),

                            Actions::make([
                                FormAction::make('printKitchenTop')
                                    ->label(__('callcenter.print.print'))
                                    ->color('primary')
                                    ->icon('heroicon-o-printer')
                                    ->action(function (callable $get, $livewire): void {
                                        $copies = max(1, (int) ($get('copies') ?? 1));
                                        $this->sendKitchenPrintFromModal($copies);

                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                                FormAction::make('closeKitchenTop')
                                    ->label(__('order.actions.cancel'))
                                    ->color('gray')
                                    ->action(function ($livewire): void {
                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                            ])
                                ->alignment('left')
                                ->extraAttributes([
                                    'class' => 'pt-6',
                                ])
                                ->columnSpan(9),
                        ]),
                    Placeholder::make('preview_render')
                        ->label(__('callcenter.print.preview'))
                        ->content(fn (callable $get): HtmlString => $this->buildKitchenPreviewIframe((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->sendKitchenPrintFromModal((int) ($data['copies'] ?? 1));
                }),

            Action::make('print_client_receipt_sidebar')
                ->label(__('callcenter.order.print_client_receipt'))
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading(__('callcenter.print.client.preview_heading'))
                ->modalDescription(__('callcenter.print.client.preview_description'))
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->fillForm(function (): array {
                    if (! $this->record?->exists) {
                        return [
                            'copies' => 1,
                            'preview_html' => '',
                        ];
                    }

                    $preview = app(KitchenDuplicatePrintService::class)
                        ->buildOperationPreview($this->record, PrintOperationCode::ClientReceipt->value, auth('admin')->user()?->name);

                    return [
                        'copies' => (int) ($preview['copies'] ?? 1),
                        'preview_html' => (string) ($preview['preview_html'] ?? ''),
                    ];
                })
                ->form([
                    Hidden::make('preview_html')
                        ->dehydrated(false),
                    Grid::make(12)
                        ->schema([
                            TextInput::make('copies')
                                ->label('Количество копий')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->step(1)
                                ->required()
                                ->columnSpan(3),

                            Actions::make([
                                FormAction::make('printClientTop')
                                    ->label('Печать')
                                    ->color('primary')
                                    ->icon('heroicon-o-printer')
                                    ->action(function (callable $get, $livewire): void {
                                        $copies = max(1, (int) ($get('copies') ?? 1));
                                        $this->sendClientReceiptFromSidebar($copies);

                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                                FormAction::make('closeClientTop')
                                    ->label('Закрыть')
                                    ->color('gray')
                                    ->action(function ($livewire): void {
                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                            ])
                                ->alignment('left')
                                ->extraAttributes([
                                    'class' => 'pt-6',
                                ])
                                ->columnSpan(9),
                        ]),
                    Placeholder::make('preview_render_client')
                        ->label('Предпросмотр чека')
                        ->content(fn (callable $get): HtmlString => $this->buildKitchenPreviewIframe((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Action::make('print_logistic_receipt_sidebar')
                ->label('Чек для логиста')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading('Предпросмотр чека для логиста')
                ->modalDescription('Проверьте содержимое чека и укажите количество копий перед печатью.')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->fillForm(function (): array {
                    if (! $this->record?->exists) {
                        return [
                            'copies' => 1,
                            'preview_html' => '',
                        ];
                    }

                    $preview = app(KitchenDuplicatePrintService::class)
                        ->buildOperationPreview($this->record, PrintOperationCode::LogisticReceipt->value, auth('admin')->user()?->name);

                    return [
                        'copies' => (int) ($preview['copies'] ?? 1),
                        'preview_html' => (string) ($preview['preview_html'] ?? ''),
                    ];
                })
                ->form([
                    Hidden::make('preview_html')
                        ->dehydrated(false),
                    Grid::make(12)
                        ->schema([
                            TextInput::make('copies')
                                ->label('Количество копий')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->step(1)
                                ->required()
                                ->columnSpan(3),

                            Actions::make([
                                FormAction::make('printLogisticTop')
                                    ->label('Печать')
                                    ->color('primary')
                                    ->icon('heroicon-o-printer')
                                    ->action(function (callable $get, $livewire): void {
                                        $copies = max(1, (int) ($get('copies') ?? 1));
                                        $this->sendLogisticReceiptFromSidebar($copies);

                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                                FormAction::make('closeLogisticTop')
                                    ->label('Закрыть')
                                    ->color('gray')
                                    ->action(function ($livewire): void {
                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                            ])
                                ->alignment('left')
                                ->extraAttributes([
                                    'class' => 'pt-6',
                                ])
                                ->columnSpan(9),
                        ]),
                    Placeholder::make('preview_render_logistic')
                        ->label('Предпросмотр чека')
                        ->content(fn (callable $get): HtmlString => $this->buildKitchenPreviewIframe((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Action::make('print_client_and_logistic_receipts_sidebar')
                ->label('Клиентский + логиста чек')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'hidden',
                    'data-hotkey' => 'cc-print-client-logistic',
                    'data-hotkey-label' => 'Alt+P',
                ])
                ->modalWidth('7xl')
                ->modalHeading('Предпросмотр клиентского и логистического чека')
                ->modalDescription('Будут напечатаны два отдельных чека на одном принтере. Проверьте содержимое и укажите количество копий.')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->fillForm(function (): array {
                    if (! $this->record?->exists) {
                        return [
                            'copies' => 1,
                            'preview_html' => '',
                        ];
                    }

                    $preview = app(KitchenDuplicatePrintService::class)
                        ->buildCombinedOperationPreview(
                            $this->record,
                            PrintOperationCode::ClientReceipt->value,
                            PrintOperationCode::LogisticReceipt->value,
                            auth('admin')->user()?->name,
                        );

                    return [
                        'copies' => (int) ($preview['copies'] ?? 1),
                        'preview_html' => (string) ($preview['preview_html'] ?? ''),
                    ];
                })
                ->form([
                    Hidden::make('preview_html')
                        ->dehydrated(false),
                    Grid::make(12)
                        ->schema([
                            TextInput::make('copies')
                                ->label('Количество копий')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->step(1)
                                ->required()
                                ->columnSpan(3),

                            Actions::make([
                                FormAction::make('printClientAndLogisticTop')
                                    ->label('Печать')
                                    ->color('primary')
                                    ->icon('heroicon-o-printer')
                                    ->action(function (callable $get, $livewire): void {
                                        $copies = max(1, (int) ($get('copies') ?? 1));
                                        $this->sendClientAndLogisticReceiptsFromSidebar($copies);

                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                                FormAction::make('closeClientAndLogisticTop')
                                    ->label('Закрыть')
                                    ->color('gray')
                                    ->action(function ($livewire): void {
                                        if (method_exists($livewire, 'unmountAction')) {
                                            $livewire->unmountAction();
                                        }
                                    }),
                            ])
                                ->alignment('left')
                                ->extraAttributes([
                                    'class' => 'pt-6',
                                ])
                                ->columnSpan(9),
                        ]),
                    Placeholder::make('preview_render_client_and_logistic')
                        ->label('Предпросмотр чеков')
                        ->content(fn (callable $get): HtmlString => $this->buildKitchenPreviewIframe((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ]),

            Action::make('send_cashalot_receipt_sidebar')
                ->label(__('callcenter.order.send_cashalot_receipt'))
                ->icon('heroicon-o-receipt-percent')
                ->color('gray')
                ->extraAttributes(['class' => 'hidden'])
                ->action(function (): void {
                    $this->sendCashalotReceiptFromSidebar();
                }),

            Action::make('open_order_journal_sidebar')
                ->label(__('order.tabs.journal'))
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->extraAttributes(['class' => 'hidden'])
                ->slideOver()
                ->modalWidth('7xl')
                ->modalHeading(__('order.journal.heading'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('order.actions.cancel'))
                ->modalContent(fn () => view('filament.callcenter.order-journal-slide-over', [
                    'record' => $this->record,
                ])),

            $this->getSaveFormAction()
                ->label(__('order.actions.save'))
                ->extraAttributes([
                    'data-hotkey' => 'cc-save',
                    'data-hotkey-label' => 'Alt+S',
                ])
                ->formId('form'),
            DeleteAction::make()
                ->label(__('order.actions.delete')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [
            //    OrderActivityWidget::class,   // покажется над формой
        ];
    }

    public function syncAddressOnSave(array $data): array
    {
        $addr = $data['address'] ?? null;               // поля формы address.*
        $select = $data['selected_address_id'] ?? null;   // '-1' = новый адрес
        $clientId = $data['clients_id'] ?? $this->record->clients_id ?? null;
        $this->zoneNeedsRefresh = false;

        if ($addr && $clientId) {
            $addr = $this->normalizeAddressCoordinates($addr);

            if ((string) $select === '-1' || empty($select)) {
                // создать новый адрес
                $new = ClientAddress::create($addr + ['client_id' => $clientId]);
                $data['client_address_id'] = $new->id;
                $this->zoneNeedsRefresh = true;
            } elseif (is_numeric($select)) {
                // обновить выбранный существующий
                if ($existing = ClientAddress::find((int) $select)) {
                    $this->zoneNeedsRefresh = $this->hasAddressChanged($existing, $addr)
                        || (int) $this->record->client_address_id !== (int) $existing->id;
                    $existing->update($addr);
                    $data['client_address_id'] = $existing->id;
                }
            }
        }

        if ($this->zoneNeedsRefresh) {
            $data['delivery_zone_id'] = null;
            $data['zone_resolution_method'] = null;
            $data['zone_resolved_at'] = null;
        }

        // эти поля не нужны для mass-assign на Order
        unset($data['address'], $data['selected_address_id']);

        return $data;
    }

    private function buildKitchenPreviewIframe(string $previewHtml): HtmlString
    {
        if (trim($previewHtml) === '') {
            return new HtmlString('<div style="font-size:12px;color:#64748b;">Предпросмотр будет показан после генерации.</div>');
        }

        $sanitized = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $previewHtml) ?? $previewHtml;
        $srcDoc = '<!doctype html><html><head><meta charset="UTF-8"><style>html,body{margin:0;padding:0;background:#f8fafc;}*{box-sizing:border-box;}</style></head><body>'
            .$sanitized
            .'</body></html>';

        $escapedSrcDoc = htmlspecialchars($srcDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new HtmlString(
            '<iframe title="Kitchen receipt preview" sandbox="allow-same-origin" srcdoc="'.$escapedSrcDoc.'" '
            .'style="width:100%;height:780px;border:1px solid #d1d5db;border-radius:8px;background:#fff;"></iframe>'
        );
    }

    protected function sendKitchenPrintFromModal(int $copies): void
    {
        if (! $this->record?->exists) {
            return;
        }

        try {
            $copies = max(1, $copies);

            $result = app(KitchenDuplicatePrintService::class)
                ->sendKitchenPrint($this->record, auth('admin')->user()?->name, 'manual', $copies);

            $this->record->refresh();

            $count = (int) ($result['count'] ?? $this->record->kitchen_print_count ?? 0);
            $title = ($result['duplicate'] ?? false)
                ? 'Дубликат отправлен на печать'
                : 'Чек кухни отправлен на печать';

            Notification::make()
                ->success()
                ->title($title)
                ->body('Количество печатей: '.$count.'. Дубликатов в задании: '.$copies)
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Ошибка печати кухни')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function sendClientReceiptFromSidebar(int $copies = 1): void
    {
        $this->sendOperationReceiptFromSidebar(PrintOperationCode::ClientReceipt->value, 'Клиентский чек отправлен на печать', $copies);
    }

    public function sendLogisticReceiptFromSidebar(int $copies = 1): void
    {
        $this->sendOperationReceiptFromSidebar(PrintOperationCode::LogisticReceipt->value, 'Чек для логиста отправлен на печать', $copies);
    }

    public function sendClientAndLogisticReceiptsFromSidebar(int $copies = 1): void
    {
        if (! $this->record?->exists) {
            return;
        }

        try {
            $result = app(KitchenDuplicatePrintService::class)
                ->sendCombinedOperationReceipts(
                    $this->record,
                    PrintOperationCode::ClientReceipt->value,
                    PrintOperationCode::LogisticReceipt->value,
                    auth('admin')->user()?->name,
                    max(1, $copies),
                );

            Notification::make()
                ->success()
                ->title('Клиентский и логистический чеки отправлены на печать')
                ->body('Client Job ID: '.(string) ($result['client_printjob_id'] ?? '-').'; Logistic Job ID: '.(string) ($result['logistic_printjob_id'] ?? '-'))
                ->send();
        } catch (
            \Throwable $exception
        ) {
            Notification::make()
                ->danger()
                ->title('Ошибка печати')
                ->body($exception->getMessage())
                ->send();
        }
    }

    public function sendCashalotReceiptFromSidebar(): void
    {
        if (! $this->record?->exists) {
            return;
        }

        $payment = data_get($this->data, 'payment', $this->record->payment);
        $fiscalizeInCashalot = (bool) data_get($this->data, 'fiscalize_in_cashalot', $this->record->fiscalize_in_cashalot);

        if (! $this->isCashalotFiscalPayment($payment) || ! $fiscalizeInCashalot) {
            Notification::make()
                ->warning()
                ->title('Cashalot: чек не відправлено')
                ->body('Для відправки потрібна оплата готівкою або POS-терміналом і увімкнений прапорець фіскалізації.')
                ->send();

            return;
        }

        try {
            $existingSuccess = $this->record->cashalotLogs()
                ->where('status', 'success')
                ->latest('id')
                ->first();

            if ($existingSuccess) {
                Notification::make()
                    ->info()
                    ->title('Чек уже фіскалізовано')
                    ->body('Фіскальний номер: ' . (string) ($existingSuccess->num_fiscal ?? '-'))
                    ->send();

                return;
            }

            $paymentValue = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

            $this->record->forceFill([
                'payment' => $paymentValue,
                'fiscalize_in_cashalot' => true,
            ])->saveQuietly();

            $this->record->refresh();
            $log = app(CashalotFiscalService::class)->fiscalizeOfflinePaidOrder($this->record);

            if ($log === null) {
                Notification::make()
                    ->warning()
                    ->title('Cashalot вимкнено')
                    ->body('Перевірте налаштування cashalot.enabled.')
                    ->send();

                return;
            }

            if ($log->status === 'success') {
                Notification::make()
                    ->success()
                    ->title('Чек ПРРО відправлено')
                    ->body('Фіскальний номер: ' . (string) ($log->num_fiscal ?? '-'))
                    ->send();

                return;
            }

            Notification::make()
                ->danger()
                ->title('Cashalot: помилка фіскалізації')
                ->body(trim((string) ($log->error_message ?? $log->error_code ?? 'Невідома помилка')))
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Cashalot: exception')
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function sendOperationReceiptFromSidebar(string $operationCode, string $successTitle, int $copies = 1): void
    {
        if (! $this->record?->exists) {
            return;
        }

        try {
            $result = app(KitchenDuplicatePrintService::class)
                ->sendOperationReceipt($this->record, $operationCode, auth('admin')->user()?->name, max(1, $copies));

            Notification::make()
                ->success()
                ->title($successTitle)
                ->body('Job ID: '.(string) ($result['printjob_id'] ?? '-'))
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Ошибка печати')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->isCashalotFiscalPayment($data['payment'] ?? null)) {
            $data['fiscalize_in_cashalot'] = false;
        }

        if (! (bool) data_get($this->data, 'date_order_manually_changed', false) && ! empty($data['dat'])) {
            $data['date_order'] = $data['dat'];
        }

        if ((bool) ($data['self_pickup'] ?? false)) {
            $data['shipping_method'] = in_array((string) ($data['shipping_method'] ?? ''), ['pickup', 'bolt', 'glovo'], true)
                ? (string) $data['shipping_method']
                : 'pickup';
            $data['shipping_price'] = 0;
            $data['shipping_total'] = 0;
        } else {
            $data['shipping_method'] = 'delivery';
        }

        return $this->syncAddressOnSave($data);
    }

    private function isCashalotFiscalPayment(mixed $payment): bool
    {
        $value = $payment instanceof PaymentMethodEnum ? $payment->value : (int) $payment;

        return in_array($value, [
            PaymentMethodEnum::CASH->value,
            PaymentMethodEnum::POS->value,
        ], true);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['dat', 'date_order'] as $field) {
            $raw = $this->record?->getRawOriginal($field);

            if (! empty($raw)) {
                $data[$field] = (string) $raw;

                continue;
            }

            if (! empty($data[$field])) {
                $data[$field] = Carbon::parse((string) $data[$field], config('app.timezone'))->toDateString();
            }
        }

        $data['address'] = is_array($data['address'] ?? null) ? $data['address'] : [];

        $data['address'] += [
            'street_place_id' => null,
            'street' => null,
            'house' => null,
            'apartment' => null,
            'intercom' => null,
            'floor' => null,
            'entrance' => null,
            'zip' => null,
            'city' => 'Київ',
            'country' => null,
            'note' => null,
            'type' => null,
            'is_private_house' => false,
            'latitude' => null,
            'longitude' => null,
            'formatted_address' => null,
        ];

        if (empty($data['date_order']) && ! empty($data['dat'])) {
            $data['date_order'] = $data['dat'];
        }

        $data['shipping_method'] = ! empty($data['self_pickup'])
            ? (in_array((string) ($data['shipping_method'] ?? ''), ['pickup', 'bolt', 'glovo'], true)
                ? (string) $data['shipping_method']
                : 'pickup')
            : 'delivery';

        return $data;
    }
    protected function afterSave(): void
    {
        $this->syncClientAddressCoordinatesFromOrder();
        app(OrderZoneSyncService::class)->syncIfNeeded($this->record, $this->zoneNeedsRefresh);
        $this->record->refresh();
        $this->record->recalculateTotalPrice();
        app(OrderPricing::class)->recalc($this->record);

        $ticket = KitchenTicket::query()->where('order_id', $this->record->id)->first();

        if ($ticket) {
            $ticket->syncItemsFromOrder();
        }
    }

    protected function normalizeAddressCoordinates(array $address): array
    {
        foreach (['latitude', 'longitude'] as $key) {
            $raw = $address[$key] ?? null;

            if ($raw === null || $raw === '') {
                continue;
            }

            $normalized = (float) str_replace(',', '.', (string) $raw);
            $address[$key] = $normalized !== 0.0 ? $normalized : $raw;
        }

        return $address;
    }

    protected function hasAddressChanged(ClientAddress $existing, array $newData): bool
    {
        $fields = [
            'street',
            'house',
            'apartment',
            'city',
            'latitude',
            'longitude',
            'street_place_id',
            'formatted_address',
        ];

        foreach ($fields as $field) {
            $old = $existing->getAttribute($field);
            $new = $newData[$field] ?? null;

            if (in_array($field, ['latitude', 'longitude'], true)) {
                $old = $old !== null && $old !== '' ? round((float) $old, 7) : null;
                $new = $new !== null && $new !== '' ? round((float) $new, 7) : null;
            } else {
                $old = is_string($old) ? trim($old) : $old;
                $new = is_string($new) ? trim($new) : $new;
            }

            if ($old !== $new) {
                return true;
            }
        }

        return false;
    }

    protected function syncClientAddressCoordinatesFromOrder(): void
    {
        if (! $this->record?->client_address_id) {
            return;
        }

        $orderAddress = (array) ($this->record->address ?? []);
        $lat = $orderAddress['latitude'] ?? null;
        $lng = $orderAddress['longitude'] ?? null;

        if (! $lat || ! $lng) {
            return;
        }

        $clientAddress = ClientAddress::find($this->record->client_address_id);

        if (! $clientAddress) {
            return;
        }

        $clientAddress->update([
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'formatted_address' => $orderAddress['formatted_address'] ?? $clientAddress->formatted_address,
            'street_place_id' => $orderAddress['street_place_id'] ?? $clientAddress->street_place_id,
        ]);
    }

    public function persistKitchenNoteInline(int $orderItemId, ?string $note = null): void
    {
        if (! $this->record?->exists || $orderItemId <= 0) {
            return;
        }

        $item = OrderItem::query()
            ->where('shop_order_id', $this->record->id)
            ->whereKey($orderItemId)
            ->first();

        if (! $item) {
            return;
        }

        $normalized = trim((string) $note);

        $item->update([
            'kitchen_note' => $normalized !== '' ? $normalized : null,
        ]);
    }
}
