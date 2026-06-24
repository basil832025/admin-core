<?php

namespace App\Filament\Resources\Shop\PaypartsBankResource\Pages;

use App\Filament\Resources\Shop\PaypartsBankResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPaypartsBank extends EditRecord
{
    protected static string $resource = PaypartsBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
