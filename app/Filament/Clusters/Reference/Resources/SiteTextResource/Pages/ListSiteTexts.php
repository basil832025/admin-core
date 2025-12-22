<?php

namespace App\Filament\Clusters\Reference\Resources\SiteTextResource\Pages;

use App\Filament\Clusters\Reference\Resources\SiteTextResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteTexts extends ListRecords
{
    protected static string $resource = SiteTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
