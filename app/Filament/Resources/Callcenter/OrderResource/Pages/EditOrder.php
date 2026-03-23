<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Filament\Resources\Callcenter\OrderResource;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasHistoryOrderActions;
use App\Filament\Resources\Callcenter\OrderResource\Concerns\HasMenuCatalogActions;
use App\Enums\PrintOperationCode;
use App\Models\Kitchen\KitchenTicket;
use App\Models\Shop\ClientAddress;
use App\Models\Shop\OrderItem;
use App\Services\Callcenter\ExternalSyncService;
use App\Services\OrderPricing;
use App\Services\PrintNode\KitchenDuplicatePrintService;
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
use Illuminate\Support\HtmlString;

class EditOrder extends EditRecord
{
    use HasHistoryOrderActions;
    use HasMenuCatalogActions;

    public ?string $pendingStatus = null;

    public ?string $prevStatus = null;

    protected static string $resource = OrderResource::class;

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

            Action::make('print_kitchen')
                ->label(function (): string {
                    $count = (int) ($this->record?->kitchen_print_count ?? 0);
                    $base = 'Печать на кухню';

                    if ($count > 1) {
                        return $base.' ('.$count.') Дубликат';
                    }

                    if ($count > 0) {
                        return $base.' ('.$count.')';
                    }

                    return $base;
                })
                ->icon('heroicon-o-printer')
                ->color(fn (): string => ((int) ($this->record?->kitchen_print_count ?? 0)) > 0 ? 'warning' : 'gray')
                ->modalHeading('Предпросмотр чека кухни')
                ->modalDescription('Проверьте содержимое чека и укажите количество дубликатов перед печатью.')
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
                                ->label('Количество дубликатов')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->step(1)
                                ->required()
                                ->columnSpan(3),

                            Actions::make([
                                FormAction::make('printKitchenTop')
                                    ->label('Печать')
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
                    Placeholder::make('preview_render')
                        ->label('Предпросмотр чека')
                        ->content(fn (callable $get): HtmlString => $this->buildKitchenPreviewIframe((string) ($get('preview_html') ?? '')))
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $this->sendKitchenPrintFromModal((int) ($data['copies'] ?? 1));
                }),

            Action::make('print_client_receipt_sidebar')
                ->label('Клиентский чек')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['class' => 'hidden'])
                ->modalHeading('Предпросмотр клиентского чека')
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
                ->extraAttributes(['class' => 'hidden'])
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

            $this->getSaveFormAction()
                ->label(__('order.actions.save'))
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

        if ($addr && $clientId) {
            $addr = $this->normalizeAddressCoordinates($addr);

            if ((string) $select === '-1' || empty($select)) {
                // создать новый адрес
                $new = ClientAddress::create($addr + ['client_id' => $clientId]);
                $data['client_address_id'] = $new->id;
            } elseif (is_numeric($select)) {
                // обновить выбранный существующий
                if ($existing = ClientAddress::find((int) $select)) {
                    $existing->update($addr);
                    $data['client_address_id'] = $existing->id;
                }
            }
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
        return $this->syncAddressOnSave($data);
    }

    /*   protected function mutateFormDataBeforeFill(array $data): array
       {
           // гарантируем массив
           $data['address'] = is_array($data['address'] ?? null) ? $data['address'] : [];

           // гарантируем все ключи, чтобы entangle не падал
           $data['address'] += [
               'street_place_id'   => null,
               'street'            => null,
               'house'             => null,
               'apartment'         => null,
               'intercom'          => null,
               'floor'             => null,
               'entrance'          => null,
               'zip'               => null,
               'city'              => 'Київ',
               'country'           => null,
               'note'              => null,
               'type'              => null,
               'is_private_house'  => false,
               'latitude'          => null,
               'longitude'         => null,
               'formatted_address' => null,
           ];

           return $data;
       }*/
    protected function afterSave(): void
    {
        $this->syncClientAddressCoordinatesFromOrder();
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
