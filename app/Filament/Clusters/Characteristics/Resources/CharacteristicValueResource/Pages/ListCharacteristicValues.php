<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicValueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use SolutionForest\FilamentTree\Concern\TreeRecords\Translatable;

class ListCharacteristicValues extends ListRecords
{
    use Translatable;
    protected static string $resource = CharacteristicValueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
