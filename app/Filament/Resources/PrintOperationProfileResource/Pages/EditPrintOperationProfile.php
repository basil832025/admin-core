<?php

namespace App\Filament\Resources\PrintOperationProfileResource\Pages;

use App\Filament\Resources\PrintOperationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrintOperationProfile extends EditRecord
{
    protected static string $resource = PrintOperationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
