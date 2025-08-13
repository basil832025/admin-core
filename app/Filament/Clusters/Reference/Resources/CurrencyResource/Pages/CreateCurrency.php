<?php

namespace App\Filament\Clusters\Reference\Resources\CurrencyResource\Pages;

use App\Filament\Clusters\Reference\Resources\CurrencyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrency extends CreateRecord
{
    protected static string $resource = CurrencyResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
