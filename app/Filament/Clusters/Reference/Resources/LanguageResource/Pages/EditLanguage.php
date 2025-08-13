<?php


namespace App\Filament\Clusters\Reference\Resources\LanguageResource\Pages;

use App\Filament\Clusters\Reference\Resources\LanguageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLanguage extends EditRecord
{
    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
  /*  protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }*/
}
