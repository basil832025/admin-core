<?php

namespace App\Filament\Resources\PrintTemplateResource\Pages;

use App\Enums\PrintTemplateType;
use App\Filament\Resources\PrintTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrintTemplate extends EditRecord
{
    protected static string $resource = PrintTemplateResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $isReport = (string) ($data['type'] ?? '') === PrintTemplateType::Report->value;
        $data['editor_mode'] = $isReport ? 'code' : 'visual';

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $isReport = (string) ($data['type'] ?? '') === PrintTemplateType::Report->value;
        $data['editor_mode'] = $isReport ? 'code' : (string) ($data['editor_mode'] ?? 'visual');
        $data['updated_by'] = auth('admin')->id() ?: auth()->id();

        return $data;
    }
}
