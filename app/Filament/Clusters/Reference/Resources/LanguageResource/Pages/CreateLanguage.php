<?php


namespace App\Filament\Clusters\Reference\Resources\LanguageResource\Pages;

use App\Filament\Clusters\Reference\Resources\LanguageResource;

use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLanguage extends CreateRecord
{
    protected static string $resource = LanguageResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
