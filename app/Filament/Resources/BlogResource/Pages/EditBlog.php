<?php

namespace App\Filament\Resources\BlogResource\Pages;

use App\Filament\Resources\BlogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables;
use Illuminate\Database\Eloquent\Model;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;
    protected static ?string $navigationLabel = 'Редактировать блог';
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
}
