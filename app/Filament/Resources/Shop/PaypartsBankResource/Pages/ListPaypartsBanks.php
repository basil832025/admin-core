<?php

namespace App\Filament\Resources\Shop\PaypartsBankResource\Pages;

use App\Filament\Resources\Shop\PaypartsBankResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaypartsBanks extends ListRecords
{
    protected static string $resource = PaypartsBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}