<?php

namespace App\Filament\Clusters\Reference\Resources\SiteTextResource\Pages;

use App\Filament\Clusters\Reference\Resources\SiteTextResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSiteText extends EditRecord
{
    protected static string $resource = SiteTextResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
