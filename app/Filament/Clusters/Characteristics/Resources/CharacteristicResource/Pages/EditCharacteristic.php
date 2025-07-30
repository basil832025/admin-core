<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use SolutionForest\FilamentTree\Concern\TreeRecords\Translatable;

class EditCharacteristic extends EditRecord
{
    use Translatable;
    protected static string $resource = CharacteristicResource::class;

    protected function getHeaderActions(): array
    {
        return [
        //    Actions\LocaleSwitcher::make(),
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
