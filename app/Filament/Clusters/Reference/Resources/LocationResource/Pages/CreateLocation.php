<?php

namespace App\Filament\Clusters\Reference\Resources\LocationResource\Pages;

use App\Filament\Clusters\Reference\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;
    protected static ?string $title = 'Создать точку';
}
