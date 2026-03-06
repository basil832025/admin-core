<?php

namespace App\Filament\Resources\Shop\PrroResource\Pages;

use App\Filament\Resources\Shop\PrroResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrro extends CreateRecord
{
    protected static string $resource = PrroResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PrroResource::prepareFormData($data);
    }
}
