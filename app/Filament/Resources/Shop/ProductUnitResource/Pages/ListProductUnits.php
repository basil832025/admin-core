<?php

namespace App\Filament\Resources\Shop\ProductUnitResource\Pages;

use App\Filament\Resources\Shop\ProductUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductUnits extends ListRecords
{
    protected static string $resource = ProductUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
