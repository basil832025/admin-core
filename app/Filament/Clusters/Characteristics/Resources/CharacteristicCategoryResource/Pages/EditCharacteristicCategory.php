<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCharacteristicCategory extends EditRecord
{
    protected static string $resource = CharacteristicCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
