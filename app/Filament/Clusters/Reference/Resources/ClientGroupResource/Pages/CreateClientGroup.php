<?php

namespace App\Filament\Clusters\Reference\Resources\ClientGroupResource\Pages;

use App\Filament\Clusters\Reference\Resources\ClientGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientGroup extends CreateRecord
{
    protected static string $resource = ClientGroupResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
