<?php

namespace App\Filament\Resources\Callcenter\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Callcenter\OrderResource;
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
                ->label('Синхронизировать каталог')
                ->icon('heroicon-m-squares-2x2')
                ->color('gray')
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncCatalogFromAllSources();

                    Notification::make()
                        ->title('Синхронизация каталога завершена')
                        ->body("Источников: {$stats['sources']}. Обработано: {$stats['processed']}. Создано: {$stats['created']}. Обновлено: {$stats['updated']}. Ошибок: {$stats['failed']}.")
                        ->success()
                        ->send();
                }),
            Action::make('syncClients')
                ->label('Синхронизировать клиентов')
                ->icon('heroicon-m-users')
                ->color('gray')
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncClientsFromAllSources(200);
                    $errors = array_slice((array) ($stats['errors'] ?? []), 0, 2);
                    $errorsText = $errors ? (' Ошибки: ' . implode(' | ', $errors)) : '';

                    Notification::make()
                        ->title('Синхронизация клиентов завершена')
                        ->body("Источников: {$stats['sources']}. Проверено: {$stats['processed']}. Новых: {$stats['created']}. Обновлено: {$stats['updated']}. Ошибок: {$stats['failed']}.{$errorsText}")
                        ->color(($stats['failed'] ?? 0) > 0 ? 'warning' : 'success')
                        ->send();
                }),
            Action::make('syncOrders')
                ->label('Получить новые заказы')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->action(function (): void {
                    $stats = app(ExternalSyncService::class)->syncOrdersFromAllSources(80);
                    $errors = array_slice((array) ($stats['errors'] ?? []), 0, 2);
                    $errorsText = $errors ? (' Ошибки: ' . implode(' | ', $errors)) : '';

                    Notification::make()
                        ->title('Синхронизация заказов завершена')
                        ->body("Источников: {$stats['sources']}. Проверено заказов: {$stats['processed']}. Новых: {$stats['created']}. Уже были: {$stats['updated']}. Ошибок: {$stats['failed']}.{$errorsText}")
                        ->color(($stats['failed'] ?? 0) > 0 ? 'warning' : 'success')
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return OrderResource::getWidgets();
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('order.tabs.all'))
                ->query(fn ($query) =>
                    $query->where('status', '!=', OrderStatus::Cart->value)
                ),

            'new' => Tab::make(OrderStatus::New->getLabel())
                ->query(fn ($query) => $query->where('status', 'new')),

            'processing' => Tab::make(OrderStatus::Processing->getLabel())
                ->query(fn ($query) => $query->where('status', 'processing')),

            'on_hold' => Tab::make(OrderStatus::OnHold->getLabel())
                ->query(fn ($query) => $query->where('status', 'on_hold')),

            'filling' => Tab::make(OrderStatus::Filling->getLabel())
                ->query(fn ($query) => $query->where('status', 'filling')),

            'molding' => Tab::make(OrderStatus::Molding->getLabel())
                ->query(fn ($query) => $query->where('status', 'molding')),

            'baking' => Tab::make(OrderStatus::Baking->getLabel())
                ->query(fn ($query) => $query->where('status', 'baking')),

            'prepared' => Tab::make(OrderStatus::Prepared->getLabel())
                ->query(fn ($query) => $query->where('status', 'prepared')),

            'assembled' => Tab::make(OrderStatus::Assembled->getLabel())
                ->query(fn ($query) => $query->where('status', 'assembled')),

            'shipped' => Tab::make(OrderStatus::Shipped->getLabel())
                ->query(fn ($query) => $query->where('status', 'shipped')),

            'delivered' => Tab::make(OrderStatus::Delivered->getLabel())
                ->query(fn ($query) => $query->where('status', 'delivered')),

            'cancelled' => Tab::make(OrderStatus::Cancelled->getLabel())
                ->query(fn ($query) => $query->where('status', 'cancelled')),

            'cart' => Tab::make(OrderStatus::Cart->getLabel())
                ->query(fn ($query) => $query->where('status', OrderStatus::Cart->value)),
        ];
    }
}
