<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicResource\Pages;

//use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
//use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Filament\Actions\CreateAction;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharacteristics extends ListRecords
{
   // use Translatable;
    protected static string $resource = CharacteristicResource::class;

    protected function getHeaderActions(): array
    {
        return [
        //    LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
