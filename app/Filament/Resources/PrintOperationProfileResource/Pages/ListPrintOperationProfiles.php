<?php

namespace App\Filament\Resources\PrintOperationProfileResource\Pages;

use App\Filament\Resources\PrintOperationProfileResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrintOperationProfiles extends ListRecords
{
    protected static string $resource = PrintOperationProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
