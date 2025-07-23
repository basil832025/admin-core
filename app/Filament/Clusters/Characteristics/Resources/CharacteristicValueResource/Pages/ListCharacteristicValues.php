<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharacteristicValues extends ListRecords
{
    protected static string $resource = CharacteristicValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
