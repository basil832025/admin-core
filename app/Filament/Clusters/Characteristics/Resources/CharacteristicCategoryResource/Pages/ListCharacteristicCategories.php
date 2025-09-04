<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\Pages;

//use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
//use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Filament\Actions\CreateAction;
use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharacteristicCategories extends ListRecords
{
 //   use Translatable;
    protected static string $resource = CharacteristicCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
          //  LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
