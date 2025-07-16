<?php

namespace App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\CharacteristicCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCharacteristicCategories extends ListRecords
{
    use ListRecords\Concerns\Translatable;
    protected static string $resource = CharacteristicCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\LocaleSwitcher::make(),
            Actions\CreateAction::make(),
        ];
    }
}
