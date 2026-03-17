<?php

namespace App\Filament\Resources\PrintTemplateResource\Pages;

use App\Filament\Resources\PrintTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPrintTemplates extends ListRecords
{
    protected static string $resource = PrintTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
