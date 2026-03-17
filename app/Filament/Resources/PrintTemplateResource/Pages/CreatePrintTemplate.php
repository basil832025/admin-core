<?php

namespace App\Filament\Resources\PrintTemplateResource\Pages;

use App\Enums\PrintTemplateType;
use App\Filament\Resources\PrintTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrintTemplate extends CreateRecord
{
    protected static string $resource = PrintTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $isReport = (string) ($data['type'] ?? '') === PrintTemplateType::Report->value;
        $data['editor_mode'] = $isReport ? 'code' : (string) ($data['editor_mode'] ?? 'visual');
        $data['created_by'] = auth('admin')->id() ?: auth()->id();
        $data['updated_by'] = $data['created_by'];

        return $data;
    }
}
