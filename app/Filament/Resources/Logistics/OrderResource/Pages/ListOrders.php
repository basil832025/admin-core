<?php

namespace App\Filament\Resources\Logistics\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Logistics\OrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
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
