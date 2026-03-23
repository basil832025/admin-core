<?php

namespace App\Filament\Resources\PrintTemplateSandboxResource\Pages;

use App\Filament\Resources\PrintTemplateResource\Pages\ListPrintTemplates;
use App\Filament\Resources\PrintTemplateSandboxResource;

class ListPrintTemplateSandboxes extends ListPrintTemplates
{
    protected static string $resource = PrintTemplateSandboxResource::class;
}
