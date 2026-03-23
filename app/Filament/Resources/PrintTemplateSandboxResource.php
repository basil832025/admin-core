<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrintTemplateSandboxResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class PrintTemplateSandboxResource extends PrintTemplateResource
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Шаблоны печати (LAB)';

    protected static ?string $modelLabel = 'Шаблон печати (LAB)';

    protected static ?string $pluralModelLabel = 'Шаблоны печати (LAB)';

    public static function getSlug(): string
    {
        return 'print-templates-lab';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintTemplateSandboxes::route('/'),
            'create' => Pages\CreatePrintTemplateSandbox::route('/create'),
            'edit' => Pages\EditPrintTemplateSandbox::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('code', 'like', 'lab\_%');
    }
}
