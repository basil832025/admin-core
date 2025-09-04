<?php

namespace App\Filament\Resources\Shop\TimeDiscountResource\Pages;

use App\Filament\Resources\Shop\TimeDiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTimeDiscounts extends ListRecords
{
    protected static string $resource = TimeDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
