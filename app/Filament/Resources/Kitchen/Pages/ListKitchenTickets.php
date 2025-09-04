<?php


namespace App\Filament\Resources\Kitchen\KitchenTicketResource\Pages;

use App\Filament\Resources\Kitchen\KitchenTicketResource;
use Filament\Actions;                           // <—
use Filament\Resources\Pages\ListRecords;

class ListKitchenTickets extends ListRecords
{
    protected static string $resource = KitchenTicketResource::class;

    protected function getHeaderActions(): array
    {
        $scope = request()->get('scope', 'current');

        return [
            Actions\Action::make('current')
                ->label('Текущие заказы')
                ->url(fn () => KitchenTicketResource::getUrl('index', ['scope' => 'current']))
                ->button()
                ->color($scope === 'current' ? 'primary' : 'gray')
                ->extraAttributes(['class' => 'text-base px-4 py-3']),   // крупнее для тача

            Actions\Action::make('archived')
                ->label('Архивные')
                ->url(fn () => KitchenTicketResource::getUrl('index', ['scope' => 'archived']))
                ->button()
                ->color($scope === 'archived' ? 'primary' : 'gray')
                ->extraAttributes(['class' => 'text-base px-4 py-3']),
        ];
    }

    // опционально
    protected function getTableContentGrid(): ?array
    {
        return ['md' => 1, 'xl' => 1];
    }
}
