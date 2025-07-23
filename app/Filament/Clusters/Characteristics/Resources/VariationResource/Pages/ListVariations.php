<?php

namespace App\Filament\Clusters\Characteristics\Resources\VariationResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\VariationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVariations extends ListRecords
{
    protected static string $resource = VariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
