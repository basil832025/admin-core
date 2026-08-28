<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodEnum;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Models\Callcenter\Order as CallcenterOrder;
use App\Models\Setting;
use App\Services\Callcenter\ExternalSyncService;
use App\Services\NovaPostApiClient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;

class ListOrders extends ListRecords
{
    private const AWAITING_PAYPARTS_STATUSES = [
        'pending_payment',
        'payment_redirected',
    ];

    protected static string $resource = OrderResource::class;

    private function isHeaderActionHidden(string $action): bool
    {
        return in_array($action, (array) config('callcenter.actions_no', []), true);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCatalog')
                ->label(__('callcenter.list.actions.sync_catalog'))
                ->icon('heroicon-m-squares-2x2')
                ->color('gray')
                ->visible(fn (): bool => ! $this->isHeaderActionHidden('sync_catalog'))
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncCatalogFromAllSources();

                    Notification::make()
                        ->title(__('callcenter.list.notifications.catalog_done_title'))
                        ->body(__('callcenter.list.notifications.catalog_done_body', [
                            'sources' => $stats['sources'] ?? 0,
                            'processed' => $stats['processed'] ?? 0,
                            'created' => $stats['created'] ?? 0,
                            'updated' => $stats['updated'] ?? 0,
                            'failed' => $stats['failed'] ?? 0,
                        ]))
                        ->success()
                        ->send();
                }),
            Action::make('syncOrders')
                ->label(__('callcenter.list.actions.sync_orders'))
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->visible(fn (): bool => ! $this->isHeaderActionHidden('sync_orders'))
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncOrdersFromAllSources(80);
                    $errors = array_slice((array) ($stats['errors'] ?? []), 0, 2);
                    $errorsText = $errors ? implode(' | ', $errors) : '';

                    Notification::make()
                        ->title(__('callcenter.list.notifications.orders_done_title'))
                        ->body(__('callcenter.list.notifications.orders_done_body', [
                            'sources' => $stats['sources'] ?? 0,
                            'processed' => $stats['processed'] ?? 0,
                            'created' => $stats['created'] ?? 0,
                            'updated' => $stats['updated'] ?? 0,
                            'failed' => $stats['failed'] ?? 0,
                            'errors' => $errorsText,
                        ]))
                        ->color(($stats['failed'] ?? 0) > 0 ? 'warning' : 'success')
                        ->send();
                }),
            Action::make('refreshNovaPostStatuses')
                ->label('Оновити статуси НП')
                ->icon('heroicon-m-truck')
                ->color('gray')
                ->visible(fn (): bool => (string) config('callcenter.order_form_mode', 'food') === 'nova_post')
                ->requiresConfirmation()
                ->modalHeading('Оновити статуси Нової пошти')
                ->modalDescription('Будуть перевірені всі замовлення з активною ТТН. Скасовані та порожні ТТН не чіпаємо.')
                ->modalSubmitActionLabel('Оновити')
                ->action(function (): void {
                    $client = app(NovaPostApiClient::class);
                    $stats = [
                        'processed' => 0,
                        'updated' => 0,
                        'failed' => 0,
                        'errors' => [],
                    ];

                    CallcenterOrder::query()
                        ->whereNotNull('nova_ttn')
                        ->where('nova_ttn', '!=', '')
                        ->where(function ($query): void {
                            $query->whereNull('nova_status')
                                ->orWhereNotIn('nova_status', ['cancelled', 'received']);
                        })
                        ->orderBy('id')
                        ->chunkById(50, function ($orders) use ($client, &$stats): void {
                            foreach ($orders as $order) {
                                $stats['processed']++;

                                try {
                                    $result = $client->updateOrderTrackingStatus($order);

                                    if (($result['old_status'] ?? null) !== ($result['new_status'] ?? null)) {
                                        $stats['updated']++;
                                    }
                                } catch (\Throwable $exception) {
                                    $stats['failed']++;

                                    if (count($stats['errors']) < 3) {
                                        $stats['errors'][] = '#' . $order->id . ': ' . $exception->getMessage();
                                    }
                                }
                            }
                        });

                    Notification::make()
                        ->title('Статуси НП оновлено')
                        ->body(sprintf(
                            'Перевірено: %d. Змінено: %d. Помилки: %d.%s',
                            $stats['processed'],
                            $stats['updated'],
                            $stats['failed'],
                            $stats['errors'] ? ' ' . implode(' | ', $stats['errors']) : ''
                        ))
                        ->color($stats['failed'] > 0 ? 'warning' : 'success')
                        ->send();
                }),
            CreateAction::make()
                ->label(__('order.actions.create'))
                ->extraAttributes([
                    'data-hotkey' => 'cc-new-order',
                    'data-hotkey-label' => 'Alt+N',
                ]),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return OrderResource::getWidgets();
    }

    public function getTabs(): array
    {
        if ((string) config('callcenter.order_form_mode', 'food') === 'nova_post') {
            return $this->getNovaPostTabs();
        }

        $tabs = [
            null => Tab::make(__('order.tabs.all'))
                ->query(fn ($query) =>
                    $query->where('status', '!=', OrderStatus::Cart->value)
                ),
        ];

        $visibleStatusValues = Setting::admin('callcenter.order_status_tabs', []);
        $orderedStatuses = collect(OrderStatus::sorted())
            ->reject(fn (OrderStatus $status): bool => $status === OrderStatus::Cart)
            ->push(OrderStatus::Cart)
            ->all();

        $allStatusValues = collect($orderedStatuses)
            ->map(fn (OrderStatus $status): string => $status->value)
            ->all();

        if (! is_array($visibleStatusValues) || $visibleStatusValues === []) {
            $visibleStatusValues = $allStatusValues;
        }

        $visibleStatusValues = array_values(array_intersect($visibleStatusValues, $allStatusValues));

        foreach ($orderedStatuses as $status) {
            if (! in_array($status->value, $visibleStatusValues, true)) {
                continue;
            }

            $statusValue = $status->value;

            if ($status === OrderStatus::Cart) {
                $tabs['awaiting_payment'] = Tab::make(__('order.tabs.awaiting_payment'))
                    ->query(fn ($query) => $this->applyAwaitingPaymentFilter($query));
            }

            $tabs[$statusValue] = Tab::make($status->getLabel())
                ->query(function ($query) use ($status, $statusValue) {
                    $query->where('status', $statusValue);

                    return $query;
                });
        }

        return $tabs;
    }

    private function getNovaPostTabs(): array
    {
        $tabs = [
            null => Tab::make(__('order.tabs.all'))
                ->query(fn ($query) => $query->where('status', '!=', OrderStatus::Cart->value)),
        ];

        $labels = OrderResource::novaPostOrderStatusLabels();
        $cartLabel = $labels[OrderStatus::Cart->value] ?? OrderStatus::Cart->getLabel();
        unset($labels[OrderStatus::Cart->value]);

        foreach ($labels as $status => $label) {
            $tabs[$status] = Tab::make($label)
                ->query(function ($query) use ($status) {
                    return $query->whereIn('status', OrderResource::novaPostOrderStatusFilterValues((string) $status));
                });
        }

        $tabs['awaiting_payment'] = Tab::make(__('order.tabs.awaiting_payment'))
            ->query(fn ($query) => $this->applyAwaitingPaymentFilter($query));

        $tabs[OrderStatus::Cart->value] = Tab::make($cartLabel)
            ->query(fn ($query) => $query->where('status', OrderStatus::Cart->value));

        return $tabs;
    }

    private function applyAwaitingPaymentFilter($query)
    {
        return OrderResource::applyAwaitingPaymentQuery($query);
    }

    private function excludeAwaitingPaymentFilter($query)
    {
        return $query->where(function ($query): void {
            $query
                ->whereNull('payment')
                ->orWhereNotIn('payment', [
                    PaymentMethodEnum::LIQPAY->value,
                    PaymentMethodEnum::PAYPARTS->value,
                ])
                ->orWhere(function ($query): void {
                    $query
                        ->where('payment', PaymentMethodEnum::PAYPARTS->value)
                        ->where(function ($query): void {
                            $query
                                ->whereNull('payparts_status')
                                ->orWhereNotIn('payparts_status', self::AWAITING_PAYPARTS_STATUSES);
                        });
                });
        });
    }
}
