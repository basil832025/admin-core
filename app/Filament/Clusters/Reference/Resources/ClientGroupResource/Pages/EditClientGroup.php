<?php

namespace App\Filament\Clusters\Reference\Resources\ClientGroupResource\Pages;

use App\Filament\Clusters\Reference\Resources\ClientGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditClientGroup extends EditRecord
{
    protected static string $resource = ClientGroupResource::class;

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
