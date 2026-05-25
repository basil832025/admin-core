<?php

namespace App\Filament\Resources;

use App\Enums\PrintTemplateType;
use App\Filament\Resources\ReportResource\Pages;
use App\Models\PrintTemplate;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = PrintTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = 'Отчеты';

    protected static ?string $modelLabel = 'Отчет';

    protected static ?string $pluralModelLabel = 'Отчеты';

    protected static function canAccessModule(): bool
    {
        $user = Filament::auth()->user();

        if (! $user) {
            return false;
        }

        return (method_exists($user, 'hasRole') && $user->hasRole(config('shield.super_admin.name', 'super_admin')))
            || $user->can('view_any_report');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccessModule();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', PrintTemplateType::Report->value)
            ->where('is_active', true)
            ->with('reportGroup');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->groups([
                Group::make('report_group_id')
                    ->label('Направление')
                    ->getTitleFromRecordUsing(fn (PrintTemplate $record): string => $record->reportGroup?->name ?? 'Без направления')
                    ->collapsible(),
            ])
            ->defaultGroup('report_group_id')
            ->columns([
                TextColumn::make('name')
                    ->label('Отчет')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reportGroup.name')
                    ->label('Направление')
                    ->placeholder('Без направления')
                    ->badge(),
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Обновлен')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_template')
                    ->label('Сформировать')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->url(fn (PrintTemplate $record): string => static::getUrl('run', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'run' => Pages\RunReport::route('/{record}/run'),
        ];
    }
}
