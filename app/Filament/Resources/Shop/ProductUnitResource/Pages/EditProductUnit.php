<?php

namespace App\Filament\Resources\Shop\ProductUnitResource\Pages;

use App\Filament\Resources\Shop\ProductUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductUnit extends EditRecord
{
    protected static string $resource = ProductUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
