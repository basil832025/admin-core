<?php

namespace App\Filament\Resources\Shop\LiqPayLogResource\Pages;

use App\Filament\Resources\Shop\LiqPayLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLiqPayLog extends EditRecord
{
    protected static string $resource = LiqPayLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
