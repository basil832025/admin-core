<?php

namespace App\Filament\Resources\BlogCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\BlogCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;

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
