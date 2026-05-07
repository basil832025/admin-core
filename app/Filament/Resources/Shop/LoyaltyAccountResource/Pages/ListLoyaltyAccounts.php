<?php

namespace App\Filament\Resources\Shop\LoyaltyAccountResource\Pages;

use App\Filament\Resources\Shop\LoyaltyAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoyaltyAccounts extends ListRecords
{
    protected static string $resource = LoyaltyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return LoyaltyAccountResource::getWidgets();
    }
}
