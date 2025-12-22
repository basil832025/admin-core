<?php

namespace App\Filament\Clusters\Reference\Resources\LocationResource\Pages;

use App\Filament\Clusters\Reference\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;
    protected static ?string $title = 'Редактировать точку';
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
