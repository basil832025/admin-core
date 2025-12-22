<?php

namespace App\Filament\Resources\Shop\LoyaltyRuleResource\Pages;

use App\Filament\Resources\Shop\LoyaltyRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoyaltyRule extends EditRecord
{
    protected static string $resource = LoyaltyRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
