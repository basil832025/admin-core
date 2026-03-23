<?php

namespace App\Filament\Resources\PrintTemplateSandboxResource\Pages;

use App\Filament\Resources\PrintTemplateResource\Pages\EditPrintTemplate;
use App\Filament\Resources\PrintTemplateSandboxResource;

class EditPrintTemplateSandbox extends EditPrintTemplate
{
    protected static string $resource = PrintTemplateSandboxResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = parent::mutateFormDataBeforeSave($data);

        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '' && ! str_starts_with($code, 'lab_')) {
            $data['code'] = 'lab_' . $code;
        }

        return $data;
    }
}
