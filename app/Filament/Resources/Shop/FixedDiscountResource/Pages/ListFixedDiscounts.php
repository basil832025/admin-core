<?php

namespace App\Filament\Resources\Shop\FixedDiscountResource\Pages;

use App\Filament\Resources\Shop\FixedDiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFixedDiscounts extends ListRecords
{
    protected static string $resource = FixedDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
