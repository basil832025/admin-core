<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCharacteristicValue extends EditRecord
{
    protected static string $resource = CharacteristicValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
