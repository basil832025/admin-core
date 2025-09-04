<?php

namespace App\Filament\Resources\Shop\TimeDiscountResource\Pages;

use App\Filament\Resources\Shop\TimeDiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTimeDiscount extends EditRecord
{
    protected static string $resource = TimeDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
