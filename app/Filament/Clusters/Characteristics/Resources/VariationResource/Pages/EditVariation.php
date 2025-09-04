<?php

namespace App\Filament\Clusters\Characteristics\Resources\VariationResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Clusters\Characteristics\Resources\VariationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVariation extends EditRecord
{
    protected static string $resource = VariationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
