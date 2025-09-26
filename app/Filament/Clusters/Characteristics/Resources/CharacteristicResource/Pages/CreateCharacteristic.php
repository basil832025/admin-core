<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCharacteristic extends CreateRecord
{
    protected static string $resource = CharacteristicResource::class;
 /*   protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }*/
}
