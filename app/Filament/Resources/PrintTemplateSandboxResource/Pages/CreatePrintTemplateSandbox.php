<?php

namespace App\Filament\Resources\PrintTemplateSandboxResource\Pages;

use App\Filament\Resources\PrintTemplateResource\Pages\CreatePrintTemplate;
use App\Filament\Resources\PrintTemplateSandboxResource;

class CreatePrintTemplateSandbox extends CreatePrintTemplate
{
    protected static string $resource = PrintTemplateSandboxResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = parent::mutateFormDataBeforeCreate($data);

        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '' && ! str_starts_with($code, 'lab_')) {
            $data['code'] = 'lab_' . $code;
        }

        $templateBody = trim((string) ($data['template_body'] ?? ''));
        if ($templateBody === '') {
            $visualBody = trim((string) ($data['template_body_visual'] ?? ''));
            $data['template_body'] = $visualBody !== ''
                ? $visualBody
                : '<div style="font-weight:700;margin-bottom:2mm;">LAB Template</div><div>{{ order_number|default("-") }}</div>';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return PrintTemplateSandboxResource::getUrl('edit', [
            'record' => $this->record,
        ]);
    }
}
