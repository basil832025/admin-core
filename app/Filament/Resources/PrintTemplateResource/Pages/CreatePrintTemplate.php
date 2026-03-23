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

        if ((string) ($data['editor_mode'] ?? 'visual') === 'visual') {
            $data['template_body'] = is_string($data['template_body_visual'] ?? null)
                ? PrintTemplateResource::formatVisualHtmlForCode((string) $data['template_body_visual'])
                : (string) ($data['template_body'] ?? '');
        }

        unset($data['template_body_visual']);
        $data['created_by'] = auth('admin')->id() ?: auth()->id();
        $data['updated_by'] = $data['created_by'];

        return $data;
    }
}
