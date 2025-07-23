<?php

namespace App\Filament\Clusters\Characteristics\Resources\VariationResource\Pages;

use App\Filament\Clusters\Characteristics\Resources\VariationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVariation extends CreateRecord
{
    protected static string $resource = VariationResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
