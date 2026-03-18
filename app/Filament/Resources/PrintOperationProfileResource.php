<?php

namespace App\Filament\Resources;

use App\Enums\PrintOperationCode;
use App\Filament\Resources\PrintOperationProfileResource\Pages;
use App\Models\PrintOperationProfile;
use App\Models\PrintTemplate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrintOperationProfileResource extends Resource
{
    protected static ?string $model = PrintOperationProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-printer';
    protected static ?string $navigationGroup = 'Настройки';
    protected static ?string $navigationLabel = 'Печать чеков';
    protected static ?string $modelLabel = 'Профиль печати';
    protected static ?string $pluralModelLabel = 'Профили печати';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                Section::make('Операция и шаблон')
                    ->schema([
                        TextInput::make('name')
                            ->label('Название профиля')
                            ->required(),

                        Select::make('operation_code')
                            ->label('Операция')
                            ->options(PrintOperationCode::options())
                            ->required()
                            ->unique(ignoreRecord: true),

                        Select::make('print_template_id')
                            ->label('Шаблон')
                            ->options(fn (): array => PrintTemplate::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),

                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpan(6),

                Section::make('Принтер и копии')
                    ->schema([
                        TextInput::make('printer_id')
                            ->label('PrintService printer selector ID')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Приоритетнее, чем имя принтера.'),

                        TextInput::make('printer_name')
                            ->label('Название принтера')
                            ->helperText('Используется как fallback, если printerId не задан.')
                            ->columnSpanFull(),

                        TextInput::make('copies')
                            ->label('Копий по умолчанию')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(20)
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpan(6),

                Section::make('Параметры бумаги (опционально, переопределяют глобальные)')
                    ->schema([
                        TextInput::make('paper_settings.width_mm')
                            ->label('Ширина (мм)')
                            ->numeric()
                            ->step(0.1),
                        TextInput::make('paper_settings.height_mm')
                            ->label('Высота (мм)')
                            ->numeric()
                            ->step(0.1),
                        TextInput::make('paper_settings.font_size_pt')
                            ->label('Размер шрифта (pt)')
                            ->numeric()
                            ->step(0.5),
                        TextInput::make('paper_settings.line_height')
                            ->label('Межстрочный интервал')
                            ->numeric()
                            ->step(0.05),
                        TextInput::make('paper_settings.margin_top_mm')
                            ->label('Отступ сверху (мм)')
                            ->numeric()
                            ->step(0.1),
                        TextInput::make('paper_settings.margin_right_mm')
                            ->label('Отступ справа (мм)')
                            ->numeric()
                            ->step(0.1),
                        TextInput::make('paper_settings.margin_bottom_mm')
                            ->label('Отступ снизу (мм)')
                            ->numeric()
                            ->step(0.1),
                        TextInput::make('paper_settings.margin_left_mm')
                            ->label('Отступ слева (мм)')
                            ->numeric()
                            ->step(0.1),
                    ])
                    ->columns(4)
                    ->columnSpan(12),

                Section::make('Привязка входных параметров')
                    ->description('Определяет, откуда брать параметры шаблона (например order_id из контекста order.id).')
                    ->schema([
                        Repeater::make('param_bindings')
                            ->label('')
                            ->schema([
                                Select::make('param_key')
                                    ->label('Параметр')
                                    ->options(function (Get $get): array {
                                        $templateId = (int) ($get('../../print_template_id') ?? 0);
                                        if ($templateId <= 0) {
                                            return [];
                                        }

                                        $schema = PrintTemplate::query()->whereKey($templateId)->value('parameters_schema');
                                        if (! is_array($schema)) {
                                            return [];
                                        }

                                        $options = [];
                                        foreach ($schema as $item) {
                                            if (! is_array($item)) {
                                                continue;
                                            }

                                            $key = trim((string) ($item['key'] ?? ''));
                                            if ($key === '') {
                                                continue;
                                            }

                                            $label = trim((string) ($item['label'] ?? ''));
                                            $options[$key] = $label !== '' ? $label . ' (' . $key . ')' : $key;
                                        }

                                        return $options;
                                    })
                                    ->searchable()
                                    ->required(),

                                Select::make('source_type')
                                    ->label('Источник')
                                    ->options([
                                        'context' => 'Контекст',
                                        'fixed' => 'Фиксированное значение',
                                        'params' => 'Параметры запуска',
                                    ])
                                    ->default('context')
                                    ->live()
                                    ->required(),

                                TextInput::make('source_path')
                                    ->label('Путь (для context/params)')
                                    ->placeholder('order.id')
                                    ->visible(fn (Get $get): bool => in_array((string) ($get('source_type') ?? ''), ['context', 'params'], true))
                                    ->required(fn (Get $get): bool => in_array((string) ($get('source_type') ?? ''), ['context', 'params'], true)),

                                TextInput::make('fixed_value')
                                    ->label('Фиксированное значение')
                                    ->visible(fn (Get $get): bool => (string) ($get('source_type') ?? '') === 'fixed')
                                    ->required(fn (Get $get): bool => (string) ($get('source_type') ?? '') === 'fixed'),

                                Toggle::make('enabled')
                                    ->label('Активно')
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(12),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Профиль')
                    ->searchable(),
                TextColumn::make('operation_code')
                    ->label('Операция')
                    ->formatStateUsing(fn ($state): string => PrintOperationCode::options()[(string) (is_object($state) && method_exists($state, 'value') ? $state->value : $state)] ?? (string) (is_object($state) && method_exists($state, 'value') ? $state->value : $state))
                    ->badge(),
                TextColumn::make('template.name')
                    ->label('Шаблон')
                    ->searchable(),
                TextColumn::make('printer_id')
                    ->label('Printer ID'),
                TextColumn::make('printer_name')
                    ->label('Имя принтера')
                    ->limit(30),
                TextColumn::make('copies')
                    ->label('Копий'),
                TextColumn::make('is_active')
                    ->label('Статус')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Активен' : 'Отключен')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintOperationProfiles::route('/'),
            'create' => Pages\CreatePrintOperationProfile::route('/create'),
            'edit' => Pages\EditPrintOperationProfile::route('/{record}/edit'),
        ];
    }
}
