<?php

namespace App\Filament\Resources\Shop\LoyaltyAccountResource\Pages;

use App\Filament\Resources\Shop\LoyaltyAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyAccount extends EditRecord
{
    protected static string $resource = LoyaltyAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
