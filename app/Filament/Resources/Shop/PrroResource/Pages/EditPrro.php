<?php

namespace App\Filament\Resources\Shop\PrroResource\Pages;

use App\Filament\Resources\Shop\PrroResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrro extends EditRecord
{
    protected static string $resource = PrroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PrroResource::prepareFormData($data, $this->record);
    }
}
