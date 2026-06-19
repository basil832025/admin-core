<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Callcenter\OrderResource;
use App\Models\Setting;
use App\Services\Callcenter\ExternalSyncService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCatalog')
                ->label(__('callcenter.list.actions.sync_catalog'))
                ->icon('heroicon-m-squares-2x2')
                ->color('gray')
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

            $tabs[$statusValue] = Tab::make($status->getLabel())
                ->query(fn ($query) => $query->where('status', $statusValue));
        }

        return $tabs;
    }
}
