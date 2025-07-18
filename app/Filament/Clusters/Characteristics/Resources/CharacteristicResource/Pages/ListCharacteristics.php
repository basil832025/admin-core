<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharacteristics extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    protected static string $resource = CharacteristicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
