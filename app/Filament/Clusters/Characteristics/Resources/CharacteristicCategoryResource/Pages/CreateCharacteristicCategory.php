<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCharacteristicCategory extends CreateRecord
{
    protected static string $resource = CharacteristicCategoryResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
