<?php

namespace App\Filament\Clusters\Characteristics\Resources\IngredientResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\IngredientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIngredients extends ListRecords
{
    protected static string $resource = IngredientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
