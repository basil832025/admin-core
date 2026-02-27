<?php

namespace App\Filament\Clusters\Reference\Resources\ClientGroupResource\Pages;

use App\Filament\Clusters\Reference\Resources\ClientGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClientGroups extends ListRecords
{
    protected static string $resource = ClientGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
