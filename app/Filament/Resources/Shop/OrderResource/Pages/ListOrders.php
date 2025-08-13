<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Filament\Resources\Shop\OrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use App\Enums\OrderStatus;
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return OrderResource::getWidgets();
    }
    public function getTabs(): array
    {
        return [
            null           => Tab::make('Все'),

            'new'          => Tab::make('Новые')
                ->query(fn ($query) => $query->where('status', 'new')),

            'processing'   => Tab::make('В обработке')
                ->query(fn ($query) => $query->where('status', 'processing')),

            'on_hold'      => Tab::make('Отложены')
                ->query(fn ($query) => $query->where('status', 'on_hold')),

            'filling'      => Tab::make('Начинка')
                ->query(fn ($query) => $query->where('status', 'filling')),

            'molding'      => Tab::make('Лепка')
                ->query(fn ($query) => $query->where('status', 'molding')),

            'baking'       => Tab::make('Печь')
                ->query(fn ($query) => $query->where('status', 'baking')),

            'prepared'     => Tab::make('Приготовлены')
                ->query(fn ($query) => $query->where('status', 'prepared')),

            'assembled'    => Tab::make('Собраны')
                ->query(fn ($query) => $query->where('status', 'assembled')),

            'shipped'      => Tab::make('Доставка')
                ->query(fn ($query) => $query->where('status', 'shipped')),

            'delivered'    => Tab::make('Доставлены')
                ->query(fn ($query) => $query->where('status', 'delivered')),

            'cancelled'    => Tab::make('Отменены')
                ->query(fn ($query) => $query->where('status', 'cancelled')),
        ];
    }

    /*    public function getTabs(): array
     {
         return [
             'all' => ListRecords\Tab::make('Все'),
             'new' => ListRecords\Tab::make('Новые')
                 ->modifyQueryUsing(fn ($query) => $query->where('status', 'new')),
             'processing' => ListRecords\Tab::make('В обработке')
                 ->modifyQueryUsing(fn ($query) => $query->where('status', 'processing')),
             'shipped' => ListRecords\Tab::make('Отправлены')
                 ->modifyQueryUsing(fn ($query) => $query->where('status', 'shipped')),
             'delivered' => ListRecords\Tab::make('Доставлены')
                 ->modifyQueryUsing(fn ($query) => $query->where('status', 'delivered')),
             'cancelled' => ListRecords\Tab::make('Отменены')
                 ->modifyQueryUsing(fn ($query) => $query->where('status', 'cancelled')),
         ];
     }*/

}
